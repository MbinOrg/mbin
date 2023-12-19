<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\ReportDto;
use App\Entity\Contracts\ReportInterface;
use App\Entity\User;
use App\Exception\SubjectHasBeenReportedException;
use App\Message\ActivityPub\Inbox\FlagMessage;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use App\Service\ActivityPubManager;
use App\Service\ReportManager;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FlagHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly ReportManager $reportManager,
        private readonly EntryRepository $entryRepository,
        private readonly EntryCommentRepository $entryCommentRepository,
        private readonly PostRepository $postRepository,
        private readonly PostCommentRepository $postCommentRepository,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FlagMessage $message): void
    {
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
                    $this->reportManager->report(ReportDto::create($subject, $message->payload['summary']), $actor);
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
        if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:]+)\/t\/([1-9][0-9]*)\/-\/comment\/([1-9][0-9]*)/", $apUrl, $matches)) {
            return $this->entryCommentRepository->findOneBy(['id' => $matches[0][3]]);
        }
        if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:]+)\/t\/([1-9][0-9]*)/", $apUrl, $matches)) {
            return $this->entryRepository->findOneBy(['id' => $matches[0][2]]);
        }
        if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:]+)\/p\/([1-9][0-9]*)\/-\/reply\/([1-9][0-9]*)/", $apUrl, $matches)) {
            return $this->postCommentRepository->findOneBy(['id' => $matches[0][3]]);
        }
        if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:]+)\/p\/([1-9][0-9]*)/", $apUrl, $matches)) {
            return $this->postRepository->findOneBy(['id' => $matches[0][2]]);
        }

        return null;
    }
}
