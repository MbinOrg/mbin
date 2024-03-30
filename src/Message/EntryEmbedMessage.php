<?php

declare(strict_types=1);

namespace App\Message;

use App\Message\Contracts\AsyncMessageInterface;

class EntryEmbedMessage implements AsyncMessageInterface
{
    public function __construct(public int $entryId)
    {
    }
}
