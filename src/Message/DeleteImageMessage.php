<?php

declare(strict_types=1);

namespace App\Message;

use App\Message\Contracts\AsyncMessageInterface;

/**
 * Attempts to delete the image entity from the database and the file from storage.
 *
 * @deprecated use DeleteImageV2Message::fromImage() instead
 */
class DeleteImageMessage implements AsyncMessageInterface
{
    public function __construct(public int $id, public bool $force = false)
    {
    }
}
