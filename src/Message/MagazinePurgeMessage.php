<?php

declare(strict_types=1);

namespace App\Message;

use App\Message\Contracts\AsyncMessageInterface;

class MagazinePurgeMessage implements AsyncMessageInterface
{
    public function __construct(public int $id, public bool $contentOnly)
    {
    }
}
