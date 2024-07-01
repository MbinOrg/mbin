<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Report\ReportApprovedEvent;
use App\Service\Notification\ReportNotificationManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportApprovedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ReportNotificationManager $notificationManager,
    ) {
    }

    public function onReportApproved(ReportApprovedEvent $reportedEvent): void
    {
        $this->notificationManager->sendReportApprovedNotification($reportedEvent->report);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportApprovedEvent::class => 'onReportApproved',
        ];
    }
}
