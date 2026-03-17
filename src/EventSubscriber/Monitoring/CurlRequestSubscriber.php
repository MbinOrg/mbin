<?php

declare(strict_types=1);

namespace App\EventSubscriber\Monitoring;

use App\Event\ActivityPub\CurlRequestBeginningEvent;
use App\Event\ActivityPub\CurlRequestFinishedEvent;
use App\Service\Monitor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CurlRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Monitor $monitor,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CurlRequestBeginningEvent::class => ['onCurlRequestBeginning'],
            CurlRequestFinishedEvent::class => ['onCurlRequestFinished'],
        ];
    }

    public function onCurlRequestBeginning(CurlRequestBeginningEvent $event): void
    {
        if (!$this->monitor->shouldRecordCurlRequests() || null === $this->monitor->currentContext) {
            return;
        }

        $this->monitor->startCurlRequest($event->targetUrl, $event->method);
    }

    public function onCurlRequestFinished(CurlRequestFinishedEvent $event): void
    {
        if (!$this->monitor->shouldRecordCurlRequests() || null === $this->monitor->currentContext) {
            return;
        }

        $this->monitor->endCurlRequest($event->url, $event->wasSuccessful, $event->exception);
    }
}
