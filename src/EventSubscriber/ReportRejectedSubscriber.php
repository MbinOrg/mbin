<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Report\ReportRejectedEvent;
use App\Service\Notification\ReportNotificationManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportRejectedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ReportNotificationManager $notificationManager,
    ) {
    }

    public function onReportRejected(ReportRejectedEvent $reportedEvent): void
    {
        $this->notificationManager->sendReportRejectedNotification($reportedEvent->report);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportRejectedEvent::class => 'onReportRejected',
        ];
    }
}
