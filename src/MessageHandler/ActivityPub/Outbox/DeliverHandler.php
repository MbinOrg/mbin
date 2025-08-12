<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\User;
use App\Exception\InstanceBannedException;
use App\Exception\InvalidApPostException;
use App\Exception\InvalidWebfingerException;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\InstanceRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPubManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsMessageHandler]
class DeliverHandler extends MbinMessageHandler
{
    public const HTTP_RESPONSE_CODE_RATE_LIMITED = 429;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly ApHttpClientInterface $client,
        private readonly ActivityPubManager $activityPubManager,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
        private readonly InstanceRepository $instanceRepository,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    /**
     * @throws InvalidApPostException
     */
    public function __invoke(DeliverMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function workWrapper(MessageInterface $message): void
    {
        $conn = $this->entityManager->getConnection();
        if (!$conn->isConnected()) {
            $conn->connect();
        }
        $conn->beginTransaction();
        try {
            $this->doWork($message);
            $conn->commit();
        } catch (InvalidApPostException $e) {
            if (400 <= $e->responseCode && 500 > $e->responseCode && self::HTTP_RESPONSE_CODE_RATE_LIMITED !== $e->responseCode) {
                $conn->rollBack();
                $this->logger->debug('{domain} responded with {code} for our request, rolling back the changes and not trying again, request: {body}', [
                    'domain' => $e->url,
                    'code' => $e->responseCode,
                    'body' => $e->payload,
                ]);
                throw new UnrecoverableMessageHandlingException('There is a problem with the request which will stay the same, so discarding', previous: $e);
            } elseif (self::HTTP_RESPONSE_CODE_RATE_LIMITED === $e->responseCode) {
                $conn->rollBack();
                // a rate limit is always recoverable
                throw new RecoverableMessageHandlingException(previous: $e);
            } else {
                // we don't roll back on an InvalidApPostException, so the failed delivery attempt gets written to the DB
                $conn->commit();
                throw $e;
            }
        } catch (TransportExceptionInterface $e) {
            // we don't roll back on an TransportExceptionInterface, so the failed delivery attempt gets written to the DB
            $conn->commit();
            throw $e;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }

        $conn->close();
    }

    /**
     * @throws InvalidApPostException
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws InstanceBannedException
     * @throws InvalidWebfingerException
     */
    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof DeliverMessage)) {
            throw new \LogicException();
        }

        $instance = $this->instanceRepository->getOrCreateInstance(parse_url($message->apInboxUrl, PHP_URL_HOST));
        if ($instance->isDead()) {
            $this->logger->debug('instance {n} is considered dead. Last successful delivery date: {dd}, failed attempts since then: {fa}', [
                'n' => $instance->domain,
                'dd' => $instance->getLastSuccessfulDeliver(),
                'fa' => $instance->getLastFailedDeliver(),
            ]);

            return;
        }

        if ('Announce' !== $message->payload['type']) {
            $url = $message->payload['object']['attributedTo'] ?? $message->payload['actor'];
        } else {
            $url = $message->payload['actor'];
        }
        $this->logger->debug("Getting Actor for url: $url");
        $actor = $this->activityPubManager->findActorOrCreate($url);

        if (!$actor) {
            $this->logger->debug('got no actor :(');

            return;
        }

        if ($actor instanceof User && $actor->isBanned) {
            $this->logger->debug('got an actor, but he is banned :(');

            return;
        }

        try {
            $this->client->post($message->apInboxUrl, $actor, $message->payload);
            if ($instance->getLastSuccessfulDeliver() < new \DateTimeImmutable('now - 5 minutes')) {
                $instance->setLastSuccessfulDeliver();
                $this->entityManager->persist($instance);
                $this->entityManager->flush();
            }
        } catch (InvalidApPostException|TransportExceptionInterface $e) {
            $instance->setLastFailedDeliver();
            $this->entityManager->persist($instance);
            $this->entityManager->flush();

            throw $e;
        }
    }
}
