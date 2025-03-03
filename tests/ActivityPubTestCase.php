<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Magazine;
use App\Entity\User;
use App\Factory\ActivityPub\AddRemoveFactory;
use App\Factory\ActivityPub\EntryCommentNoteFactory;
use App\Factory\ActivityPub\FlagFactory;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\ActivityPub\PostCommentNoteFactory;
use App\Factory\ActivityPub\PostNoteFactory;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use App\Service\ActivityPub\Wrapper\FollowResponseWrapper;
use App\Service\ActivityPub\Wrapper\FollowWrapper;
use App\Service\ActivityPub\Wrapper\LikeWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\ActivityPub\Wrapper\UpdateWrapper;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Uid\Uuid;

class ActivityPubTestCase extends WebTestCase
{
    use MatchesSnapshots;

    protected User $owner;
    protected Magazine $magazine;
    protected User $user;

    protected PersonFactory $personFactory;
    protected GroupFactory $groupFactory;
    protected EntryCommentNoteFactory $entryCommentNoteFactory;
    protected PostNoteFactory $postNoteFactory;
    protected PostCommentNoteFactory $postCommentNoteFactory;
    protected AddRemoveFactory $addRemoveFactory;
    protected CreateWrapper $createWrapper;
    protected UpdateWrapper $updateWrapper;
    protected DeleteWrapper $deleteWrapper;
    protected LikeWrapper $likeWrapper;
    protected FollowWrapper $followWrapper;
    protected AnnounceWrapper $announceWrapper;
    protected UndoWrapper $undoWrapper;
    protected FollowResponseWrapper $followResponseWrapper;
    protected FlagFactory $flagFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->getUserByUsername('owner', addImage: false);
        $this->magazine = $this->getMagazineByName('test', $this->owner);
        $this->user = $this->getUserByUsername('user', addImage: false);

        $this->personFactory = $this->getService(PersonFactory::class);
        $this->groupFactory = $this->getService(GroupFactory::class);
        $this->entryCommentNoteFactory = $this->getService(EntryCommentNoteFactory::class);
        $this->postNoteFactory = $this->getService(PostNoteFactory::class);
        $this->postCommentNoteFactory = $this->getService(PostCommentNoteFactory::class);
        $this->addRemoveFactory = $this->getService(AddRemoveFactory::class);
        $this->createWrapper = $this->getService(CreateWrapper::class);
        $this->updateWrapper = $this->getService(UpdateWrapper::class);
        $this->deleteWrapper = $this->getService(DeleteWrapper::class);
        $this->likeWrapper = $this->getService(LikeWrapper::class);
        $this->followWrapper = $this->getService(FollowWrapper::class);
        $this->announceWrapper = $this->getService(AnnounceWrapper::class);
        $this->undoWrapper = $this->getService(UndoWrapper::class);
        $this->followResponseWrapper = $this->getService(FollowResponseWrapper::class);
        $this->flagFactory = $this->getService(FlagFactory::class);
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    private function getService(string $className)
    {
        return $this->getContainer()->get($className);
    }

    protected function getDefaultUuid(): Uuid
    {
        return new Uuid('00000000-0000-0000-0000-000000000000');
    }

    protected function getSnapshotDirectory(): string
    {
        return \dirname((new \ReflectionClass($this))->getFileName()).
            DIRECTORY_SEPARATOR.
            'JsonSnapshots';
    }
}
