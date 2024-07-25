<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Moderator;
use App\Entity\Report;
use App\Factory\ActivityPub\FlagFactory;
use App\Message\ActivityPub\Outbox\FlagMessage;
use App\Repository\ReportRepository;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FlagHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
        private readonly ReportRepository $reportRepository,
        private readonly FlagFactory $factory,
        private readonly LoggerInterface $logger,
        private readonly DeliverManager $deliverManager,
    ) {
    }

    public function __invoke(FlagMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(FlagMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->logger->debug('got a FlagMessage');
        $report = $this->reportRepository->find($message->reportId);
        $this->logger->debug('found the report: '.json_encode($report));
        $inboxes = $this->getInboxUrls($report);
        if (0 === \sizeof($inboxes)) {
            $this->logger->info("couldn't find any inboxes to send the FlagMessage to");

            return;
        }

        $activity = $this->factory->build($report, $this->factory->getPublicUrl($report->getSubject()));
        $this->deliverManager->deliver($inboxes, $activity);
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

        if ($report->reported->apId and !\in_array($report->reported->apInboxUrl, $urls)) {
            $urls[] = $report->reported->apInboxUrl;
        }

        return $urls;
    }
}
