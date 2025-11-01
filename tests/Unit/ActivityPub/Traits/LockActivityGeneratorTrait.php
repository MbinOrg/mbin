<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait LockActivityGeneratorTrait
{
    public function getLockEntryActivityByAuthor(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->lockFactory->build($this->user, $entry);
    }

    public function getLockEntryActivityByModerator(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->lockFactory->build($this->owner, $entry);
    }

    public function getLockPostActivityByAuthor(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->lockFactory->build($this->user, $post);
    }

    public function getLockPostActivityByModerator(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->lockFactory->build($this->owner, $post);
    }
}
