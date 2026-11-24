<?php

declare(strict_types=1);

namespace App\Exception;

use App\Utils\LogContext;

final class InvalidApPostException extends \Exception
{
    public function __construct(public ?string $messageStart = '', public ?string $url = null, public ?int $responseCode = null, public ?array $payload = null, int $code = 0, ?\Throwable $previous = null)
    {
        $message = $this->messageStart;
        $additions = [];
        if ($url) {
            $additions[] = LogContext::host($url) ?? 'unknown host';
        }
        if ($responseCode) {
            $additions[] = "status code: $responseCode";
        }
        // Messenger logs the exception message on retries. The payload can be large and contain private content.
        // Temporarily enable this only for local debugging.
        // $additions[] = json_encode($this->payload);
        if (0 < \sizeof($additions)) {
            $message .= ': '.implode(', ', $additions);
        }
        parent::__construct($message, $code, $previous);
    }
}
