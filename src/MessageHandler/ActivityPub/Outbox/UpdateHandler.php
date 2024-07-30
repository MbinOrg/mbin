<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\ActivityPub\Outbox\UpdateMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPubManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class UpdateHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly CreateWrapper $createWrapper,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly SettingsManager $settingsManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(UpdateMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof UpdateMessage)) {
            throw new \LogicException();
        }

        $entity = $this->entityManager->getRepository($message->type)->find($message->id);

        $activity = $this->createWrapper->build($entity);
        $activity['id'] = $this->urlGenerator->generate(
            'ap_object',
            ['id' => Uuid::v4()->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $activity['type'] = 'Update';
        $activity['object']['updated'] = $entity->editedAt
            ? $entity->editedAt->format(DATE_ATOM)
            : (new \DateTime())->format(DATE_ATOM);

        $this->deliver(array_filter($this->userRepository->findAudience($entity->user)), $activity);
        $this->deliver(
            array_filter($this->activityPubManager->createInboxesFromCC($activity, $entity->user)),
            $activity
        );
        $this->deliver(array_filter($this->magazineRepository->findAudience($entity->magazine)), $activity);
    }

    private function deliver(array $followers, array $activity): void
    {
        foreach ($followers as $follower) {
            if (!$follower) {
                continue;
            }
            if (\is_string($follower)) {
                $this->bus->dispatch(new DeliverMessage($follower, $activity));
                continue;
            }
            if ($follower->apInboxUrl) {
                $this->bus->dispatch(new DeliverMessage($follower->apInboxUrl, $activity));
            }
        }
    }
}
