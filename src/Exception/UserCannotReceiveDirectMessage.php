<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\User;

final class UserCannotReceiveDirectMessage extends \Exception
{
    public function __construct(User $author, User $recipient, int $code = 0, ?\Throwable $previous = null)
    {
        $message = "$author->username tried to sent a direct message to $recipient->username but they cannot receive it";
        parent::__construct($message, $code, $previous);
    }
}
