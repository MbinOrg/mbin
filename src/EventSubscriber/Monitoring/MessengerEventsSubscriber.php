<?php

declare(strict_types=1);

namespace App\EventSubscriber\Monitoring;

use App\Service\Monitor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Heavily inspired by https://github.com/inspector-apm/inspector-symfony/blob/master/src/Listeners/MessengerEventsSubscriber.php.
 */
readonly class MessengerEventsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Monitor $monitor,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onWorkerMessageReceived',
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
        ];
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        if (!$this->monitor->shouldRecord()) {
            return;
        }
        $message = $event->getEnvelope()->getMessage();
        $this->monitor->startNewExecutionContext('messenger', 'anonymous', \get_class($message), $event->getReceiverName());
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if (!$this->monitor->shouldRecord()) {
            return;
        }
        $throwable = $event->getThrowable();
        $this->monitor->currentContext->exception = \get_class($throwable);
        $this->monitor->currentContext->stacktrace = $throwable->getTraceAsString();
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        if (!$this->monitor->shouldRecord()) {
            return;
        }
        $this->monitor->endCurrentExecutionContext();
    }
}
