<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ReportInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Moderator;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\Report;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\ActivityPub\Outbox\FlagMessage;
use App\Repository\ReportRepository;
use App\Service\SettingsManager;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class FlagHandler
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SettingsManager $settingsManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ReportRepository $reportRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FlagMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->logger->debug('got a FlagMessage');
        $report = $this->reportRepository->find($message->reportId);
        $this->logger->debug('found the report: '.json_encode($report));
        $inboxes = $this->getInboxUrls($report);
        if (0 === \sizeof($inboxes)) {
            $this->logger->debug("couldn't find any inboxes to send the FlagMessage to");

            return;
        }

        $activity = $this->build($report, $this->getPublicUrl($report->getSubject()));
        foreach ($inboxes as $inbox) {
            $this->logger->debug("Sending a flag message to: '$inbox'. Payload: ".json_encode($activity));
            $this->bus->dispatch(new DeliverMessage($inbox, $activity));
        }
    }

    private function getPublicUrl(ReportInterface $subject): string
    {
        $publicUrl = $subject->getApId();
        if ($publicUrl) {
            return $publicUrl;
        }

        return match (str_replace('Proxies\\__CG__\\', '', \get_class($subject))) {
            Entry::class => $this->urlGenerator->generate('ap_entry', [
                'magazine_name' => $subject->magazine->name,
                'entry_id' => $subject->getId(),
            ]),
            EntryComment::class => $this->urlGenerator->generate('ap_entry_comment', [
                'magazine_name' => $subject->magazine->name,
                'entry_id' => $subject->entry->getId(),
                'comment_id' => $subject->getId(),
            ]),
            Post::class => $this->urlGenerator->generate('ap_post', [
                'magazine_name' => $subject->magazine->name,
                'post_id' => $subject->getId(),
            ]),
            PostComment::class => $this->urlGenerator->generate('ap_post_comment', [
                'magazine_name' => $subject->magazine->name,
                'post_id' => $subject->post->getId(),
                'comment_id' => $subject->getId(),
            ]),
            default => throw new \LogicException("can't handle ".\get_class($subject)),
        };
    }

    #[ArrayShape([
        '@context' => 'mixed',
        'id' => 'string',
        'type' => 'string',
        'actor' => 'mixed',
        'to' => 'mixed',
        'object' => 'string',
        'audience' => 'string',
        'summary' => 'string',
    ])]
    private function build(Report $report, string $objectUrl): array
    {
        $context = [ActivityPubActivityInterface::CONTEXT_URL];

        return [
            '@context' => $context,
            'id' => 'https://' . $this->settingsManager->get('KBIN_DOMAIN').'/activities/reports/' . $report->getId(),
            'type' => 'Flag',
            'actor' => $report->reporting->apPublicUrl ?? $this->urlGenerator->generate('ap_user', ['username' => $report->reporting->username], UrlGeneratorInterface::ABSOLUTE_URL),
            'to' => [$report->magazine->apPublicUrl ?? $this->urlGenerator->generate('ap_magazine', ['name' => $report->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL)],
            'object' => $objectUrl,
            // apAttributedToUrl is not a standardized field, so it is not implemented by every software that supports groups.
            // Some don't have moderation at all, so it will probably remain optional in the future.
            'audience' => $report->magazine->apId ? $report->magazine->apAttributedToUrl : $this->urlGenerator->generate('ap_magazine_moderators', ['name' => $report->magazine->name]),
            'summary' => $report->reason,
        ];
    }

    /**
     * @return string[]
     */
    private function getInboxUrls(Report $report): array
    {
        $urls = [];

        if (null === $report->magazine->apId) {
            foreach ($report->magazine->moderators as /* @var Moderator $moderator */ $moderator) {
                if ($moderator->user->apId and !\in_array($moderator->user->apInboxUrl, $urls)) {
                    $urls[] = $moderator->user->apInboxUrl;
                }
            }
        } else {
            $urls[] = $report->magazine->apInboxUrl;
        }

        return $urls;
    }
}
