<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidUserPublicKeyException extends \Exception
{
    /**
     * @param string $apProfileId the url of the user where the key cannot be extracted, is malformed, or does not exist
     */
    public function __construct(public string $apProfileId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = "Unable to extract public key for '$apProfileId'.";
        parent::__construct($message, $code, $previous);
    }
}
