<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\ReportDto;
use App\Entity\Contracts\ReportInterface;
use App\Entity\User;
use App\Exception\SubjectHasBeenReportedException;
use App\Message\ActivityPub\Inbox\FlagMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use App\Service\ActivityPubManager;
use App\Service\ReportManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FlagHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ReportManager $reportManager,
        private readonly EntryRepository $entryRepository,
        private readonly EntryCommentRepository $entryCommentRepository,
        private readonly PostRepository $postRepository,
        private readonly PostCommentRepository $postCommentRepository,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(FlagMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof FlagMessage)) {
            throw new \LogicException();
        }
        $this->logger->debug('Got FlagMessage: '.json_encode($message));
        $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
        $object = $message->payload['object'];
        $objects = \is_array($object) ? $object : [$object];
        if (!$actor instanceof User) {
            throw new \LogicException("could not find a user actor on url '{$message->payload['actor']}'");
        }
        foreach ($objects as $item) {
            if (!\is_string($item)) {
                continue;
            }

            if ($this->settingsManager->isLocalUrl($item)) {
                $subject = $this->findLocalSubject($item);
            } else {
                $subject = $this->findRemoteSubject($item);
            }
            if (null !== $subject) {
                try {
                    $reason = null;
                    if (\array_key_exists('summary', $message->payload) && \is_string($message->payload['summary'])) {
                        $reason = $message->payload['summary'];
                    }
                    $this->reportManager->report(ReportDto::create($subject, $reason), $actor);
                } catch (SubjectHasBeenReportedException) {
                }
            } else {
                $this->logger->warning("could not find the subject of a report: '$item'");
            }
        }
    }

    private function findRemoteSubject(string $apUrl): ?ReportInterface
    {
        $entry = $this->entryRepository->findOneBy(['apId' => $apUrl]);
        $entryComment = null;
        $post = null;
        $postComment = null;
        if (!$entry) {
            $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $apUrl]);
        }
        if (!$entry and !$entryComment) {
            $post = $this->postRepository->findOneBy(['apId' => $apUrl]);
        }
        if (!$entry and !$entryComment and !$post) {
            $postComment = $this->postCommentRepository->findOneBy(['apId' => $apUrl]);
        }

        return $entry ?? $entryComment ?? $post ?? $postComment;
    }

    private function findLocalSubject(string $apUrl): ?ReportInterface
    {
        $matches = null;
        if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:@.]+)\/t\/([1-9][0-9]*)\/-\/comment\/([1-9][0-9]*)/", $apUrl, $matches)) {
            return $this->entryCommentRepository->findOneBy(['id' => $matches[3][0]]);
        }
        if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:@.]+)\/t\/([1-9][0-9]*)/", $apUrl, $matches)) {
            return $this->entryRepository->findOneBy(['id' => $matches[2][0]]);
        }
        if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:@.]+)\/p\/([1-9][0-9]*)\/-\/reply\/([1-9][0-9]*)/", $apUrl, $matches)) {
            return $this->postCommentRepository->findOneBy(['id' => $matches[3][0]]);
        }
        if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:@.]+)\/p\/([1-9][0-9]*)/", $apUrl, $matches)) {
            return $this->postRepository->findOneBy(['id' => $matches[2][0]]);
        }

        return null;
    }
}
