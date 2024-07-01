<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Report\SubjectReportedEvent;
use App\Message\ActivityPub\Outbox\FlagMessage;
use App\Service\Notification\ReportNotificationManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SubjectReportedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly ReportNotificationManager $notificationManager,
    ) {
    }

    public function onSubjectReported(SubjectReportedEvent $reportedEvent): void
    {
        $this->logger->debug($reportedEvent->report->reported->username.' was reported for '.$reportedEvent->report->reason);
        $this->notificationManager->sendReportCreatedNotification($reportedEvent->report);
        if (!$reportedEvent->report->magazine->apId and 'random' !== $reportedEvent->report->magazine->name) {
            return;
        }

        if ($reportedEvent->report->magazine->apId) {
            $this->logger->debug('was on a remote magazine, dispatching a new FlagMessage');
        } elseif ('random' === $reportedEvent->report->magazine->name) {
            $this->logger->debug('was on the random magazine, dispatching a new FlagMessage');
        }
        $this->bus->dispatch(new FlagMessage($reportedEvent->report->getId()));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SubjectReportedEvent::class => 'onSubjectReported',
        ];
    }
}
