<?php

declare(strict_types=1);

namespace App\Command\Update\Async;

use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\Contracts\AsyncMessageInterface;

class NoteVisibilityMessage implements AsyncMessageInterface
{
    /**
     * @param class-string<Post|PostComment> $class
     */
    public function __construct(public int $id, public string $class)
    {
    }
}
