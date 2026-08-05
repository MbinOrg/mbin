<?php

declare(strict_types=1);

namespace App\Message;

use App\Message\Contracts\AsyncMessageInterface;

/**
 * Attempts to delete the image entity from the database, but not the file from storage.
 */
class DeleteImageMessage implements AsyncMessageInterface
{
    public function __construct(public int $id, public bool $force = false)
    {
    }
}
