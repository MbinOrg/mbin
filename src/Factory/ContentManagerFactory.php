<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Service\Contracts\ContentManagerInterface;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\PostCommentManager;
use App\Service\PostManager;

class ContentManagerFactory
{
    public function __construct(
        private readonly EntryManager $entryManager,
        private readonly EntryCommentManager $entryCommentManager,
        private readonly PostManager $postManager,
        private readonly PostCommentManager $postCommentManager,
    ) {
    }

    public function createManager(ContentInterface $subject): ContentManagerInterface
    {
        if ($subject instanceof Entry) {
            return $this->entryManager;
        } elseif ($subject instanceof EntryComment) {
            return $this->entryCommentManager;
        } elseif ($subject instanceof Post) {
            return $this->postManager;
        } elseif ($subject instanceof PostComment) {
            return $this->postCommentManager;
        }
        throw new \LogicException("Unsupported subject type: '".\get_class($subject)."'");
    }
}
