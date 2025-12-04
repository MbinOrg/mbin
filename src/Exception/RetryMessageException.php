<?php

declare(strict_types=1);

namespace App\Exception;

use JetBrains\PhpStorm\Pure;

/**
 * Thrown when something is not as expected but might succeed when retried later.
 */
final class RetryMessageException extends \Exception
{
    #[Pure]
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
