<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Message;
use App\Event\Report\SubjectReportedEvent;
use App\Message\ActivityPub\Outbox\FlagMessage;
use App\Service\Notification\ReportNotificationManager;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SubjectReportedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly ReportNotificationManager $notificationManager,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function onSubjectReported(SubjectReportedEvent $reportedEvent): void
    {
        $this->logger->debug($reportedEvent->report->reported->username.' was reported for '.$reportedEvent->report->reason);
        $this->notificationManager->sendReportCreatedNotification($reportedEvent->report);

        $sendFlag = false;
        if($reportedEvent->report->magazine === null) {
            // is a message -> check if remote
            $message = $reportedEvent->report->getSubject();
            if($message instanceof Message) {
                if($message->getApId() !== null && !$this->settingsManager->isLocalUrl($message->getApId())) {
                    $this->logger->debug('was a message from a remote instance, dispatching a new FlagMessage');
                    $sendFlag = true;
                }
            } else {
                $this->logger->error('got a report with magazine === null but it was not a MessageReport');
            }
        } elseif ($reportedEvent->report->magazine->apId) {
            $this->logger->debug('was on a remote magazine, dispatching a new FlagMessage');
            $sendFlag = true;
        } elseif ('random' === $reportedEvent->report->magazine->name) {
            $this->logger->debug('was on the random magazine, dispatching a new FlagMessage');
            $sendFlag = true;
        }
        if ($sendFlag) {
            $this->bus->dispatch(new FlagMessage($reportedEvent->report->getId()));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SubjectReportedEvent::class => 'onSubjectReported',
        ];
    }
}
