<?php

declare(strict_types=1);

namespace App\Message;

use App\Message\Contracts\AsyncMessageInterface;

/**
 * sent when a new user was initially set up and further configurations can be performed asynchronously.
 */
class UserSetupMessage implements AsyncMessageInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(public int $userId)
    {
    }
}
