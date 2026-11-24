<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidApPostException;
use App\Exception\InvalidApSignatureException;
use App\Utils\LogContext;
use Doctrine\DBAL\Exception\DriverException;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMonologProcessor(channel: 'messenger')]
final class MessengerFailureLogProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if ('messenger' !== $record->channel || !str_starts_with($record->message, 'Error thrown while handling message {class}. ')) {
            return $record;
        }

        $retrying = str_contains($record->message, 'Sending for retry #{retryCount}');
        if (!$retrying && !str_contains($record->message, 'Removing from transport after {retryCount} retries')) {
            return $record;
        }

        // Messenger supplies the error text and exception separately. Either can contain payloads or SQL.
        $context = array_intersect_key($record->context, array_flip(['class', 'message_id', 'retryCount', 'delay']));
        if (\is_string($context['message_id'] ?? null)) {
            $context['message_id'] = mb_strcut($context['message_id'], 0, 160, 'UTF-8');
        }

        $cause = $record->context['exception'] ?? null;
        while (($cause instanceof HandlerFailedException || $cause instanceof RecoverableMessageHandlingException || $cause instanceof UnrecoverableMessageHandlingException) && $cause->getPrevious()) {
            $cause = $cause->getPrevious();
        }

        if ($cause instanceof \Throwable) {
            $context['exception_type'] = $cause::class;
            $context += $this->causeContext($cause);
        }

        return $record->with(
            message: '[SendFailedMessageForRetryListener::onMessageFailed] '.$this->describeFailure($context, $retrying),
            context: $context,
        );
    }

    private function describeFailure(array $context, bool $retrying): string
    {
        $name = $this->shortClassName($context['class'] ?? 'message');
        $retryCount = $context['retryCount'] ?? 0;
        $destination = isset($context['destination_host']) ? ' (to '.$context['destination_host'].')' : '';
        $message = $retrying
            ? "Retry #$retryCount for $name$destination"
            : "Removed $name$destination from transport after $retryCount retries";
        if ($retrying && isset($context['delay'])) {
            $message .= " in {$context['delay']} ms";
        }

        $cause = match (true) {
            isset($context['http_status']) => 'HTTP '.$context['http_status'],
            isset($context['sqlstate']) => 'SQLSTATE '.$context['sqlstate'],
            isset($context['reason']) => str_replace('_', ' ', $context['reason']),
            isset($context['exception_type']) => $this->shortClassName($context['exception_type']),
            default => null,
        };

        return null === $cause ? $message : $message.': '.$cause;
    }

    private function shortClassName(string $class): string
    {
        return substr((string) strrchr('\\'.$class, '\\'), 1);
    }

    private function causeContext(\Throwable $cause): array
    {
        if ($cause instanceof InvalidApPostException) {
            // The destination distinguishes deliveries that failed with the same HTTP status.
            $context = array_filter([
                'http_status' => $cause->responseCode,
                'destination_host' => LogContext::host($cause->url),
            ], static fn (int|string|null $value): bool => null !== $value);
            if ($context) {
                return $context;
            }
        }
        if ($cause instanceof DriverException && null !== $cause->getSQLState()) {
            return ['sqlstate' => $cause->getSQLState()];
        }
        if ($cause instanceof InvalidApSignatureException && str_starts_with($cause->getMessage(), 'Supplied key domain does not match')) {
            return ['reason' => 'signature_key_domain_mismatch'];
        }
        if ($cause instanceof UnrecoverableMessageHandlingException) {
            $reason = match ($cause->getMessage()) {
                'Activity message from was empty or invalid JSON' => 'invalid_activity_json',
                'Activity message has no id field' => 'missing_activity_id',
                default => null,
            };
            if (null !== $reason) {
                return ['reason' => $reason];
            }
        }

        return ['origin' => basename($cause->getFile()).':'.$cause->getLine()];
    }
}
