<?php

declare(strict_types=1);

namespace App\Utils;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;

/**
 * This class is meant to be used instead of \Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener.
 * The difference is that the ErrorDetailsStamp will be created without FlattenException.
 * This is important because FlattenException contains stack trace which can be quite large,
 * potentially causing AmqpSender to throw "Library error: table too large for buffer".
 *
 * @source https://github.com/symfony/symfony/issues/45944
 *
 * @author https://github.com/enumag
 */
final class AddErrorDetailsStampListener implements EventSubscriberInterface
{
    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $throwable = $event->getThrowable();
        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getPrevious();
        }

        if (null === $throwable) {
            return;
        }

        $stamp = new ErrorDetailsStamp($throwable::class, $throwable->getCode(), $throwable->getMessage());

        $previousStamp = $event->getEnvelope()->last(ErrorDetailsStamp::class);

        // Do not append duplicate information
        if (null === $previousStamp || !$previousStamp->equals($stamp)) {
            $event->addStamps($stamp);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must have higher priority than SendFailedMessageForRetryListener
            WorkerMessageFailedEvent::class => ['onMessageFailed', 200],
        ];
    }
}
