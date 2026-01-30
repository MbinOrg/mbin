<?php

declare(strict_types=1);

namespace App\Exception;

final class InvalidApPostException extends \Exception
{
    public function __construct(public ?string $messageStart = '', public ?string $url = null, public ?int $responseCode = null, public ?array $payload = null, int $code = 0, ?\Throwable $previous = null)
    {
        $message = $this->messageStart;
        $additions = [];
        if ($url) {
            $additions[] = $url;
        }
        if ($responseCode) {
            $additions[] = "status code: $responseCode";
        }
        if ($payload) {
            $jsonPayload = json_encode($this->payload);
            $additions[] = $jsonPayload;
        }
        if (0 < \sizeof($additions)) {
            $message .= ': '.implode(', ', $additions);
        }
        parent::__construct($message, $code, $previous);
    }
}
