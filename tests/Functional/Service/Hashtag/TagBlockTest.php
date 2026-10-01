<?php
declare(strict_types=1);

namespace App\Tests\Functional\Service\Hashtag;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\PageView\EntryCommentPageView;
use App\PageView\EntryPageView;
use App\PageView\PostCommentPageView;
use App\PageView\PostPageView;
use App\Repository\Criteria;
use App\Tests\WebTestCase;

class TagBlockTest extends WebTestCase
{
    public function testBlock()
    {
        $user1 = $this->getUserByUsername('John Doe');
        $user2 = $this->getUserByUsername('Jane Doe');
        $tagNeutral = $this->getHashtag('abc');
        $tagBlocked = $this->getHashtag('def');

        $this->tagManager->block($user1, $tagBlocked);

        self::assertCount(1, $user1->blockedHashtags);
        self::assertSame($tagBlocked->tag, $user1->blockedHashtags[0]->hashtag->tag);
        self::assertCount(0, $user2->blockedHashtags);
    }

    public function testUnblock()
    {
        $user1 = $this->getUserByUsername('John Doe');
        $user2 = $this->getUserByUsername('Jane Doe');
        $tag1 = $this->getHashtag('abc');
        $tag2 = $this->getHashtag('def');

        $this->tagManager->block($user1, $tag1);
        $this->tagManager->block($user1, $tag2);
        $this->tagManager->block($user2, $tag1);

        $this->tagManager->unblock($user1, $tag1);

        self::assertCount(1, $user1->blockedHashtags);
        self::assertSame($tag2->tag, $user1->blockedHashtags->first()->hashtag->tag);
        self::assertCount(1, $user2->blockedHashtags);
        self::assertSame($tag1->tag, $user2->blockedHashtags->first()->hashtag->tag);
    }

    public function testBlockedHashtagIsHiddenInCombinedWithCache()
    {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('testBlockedHashtagIsHiddenInCombinedWithCache');
        $entryShowing = $this->createEntry('showing', $magazine, $contentCreator, body: 'some text #wanted');
        $entryHidden = $this->createEntry('hidden', $magazine, $contentCreator, body: 'some text #notWanted');
        $entryCommentShowing = $this->createEntryComment('some text #wanted', $entryShowing, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notWanted', $entryShowing, $contentCreator);
        $postShowing = $this->createPost('some text #wanted', $magazine, $contentCreator);
        $postHidden = $this->createPost('some text #notWanted', $magazine, $contentCreator);
        $postCommentShowing = $this->createPostComment('some text #wanted', $postShowing, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notWanted', $postShowing, $contentCreator);
        $this->setContentTime($entryHidden, $entryShowing, 2);
        $this->setContentTime($entryCommentShowing, $entryShowing, 4);
        $this->setContentTime($entryCommentHidden, $entryShowing, 6);
        $this->setContentTime($postShowing, $entryShowing, 8);
        $this->setContentTime($postHidden, $entryShowing, 10);
        $this->setContentTime($postCommentShowing, $entryShowing, 12);
        $this->setContentTime($postCommentHidden, $entryShowing, 14);

        $user->follow($contentCreator);
        $this->magazineManager->subscribe($magazine, $user);
        $this->tagManager->block($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->includeBoosts = true;
        $criteria->perPage = 5;
        $criteria->fetchCachedItems($this->sqlHelpers, $user);

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertInstanceOf(Entry::class, $result[0]);
        self::assertSame($entryShowing->getId(), $result[0]->getId());
        self::assertInstanceOf(EntryComment::class, $result[1]);
        self::assertSame($entryCommentShowing->getId(), $result[1]->getId());
        self::assertInstanceOf(Post::class, $result[2]);
        self::assertSame($postShowing->getId(), $result[2]->getId());
        self::assertInstanceOf(PostComment::class, $result[3]);
        self::assertSame($postCommentShowing->getId(), $result[3]->getId());
        self::assertCount(4, $result);
    }

    public function testBlockedHashtagIsHiddenInCombinedWithoutCache()
    {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('testBlockedHashtagIsHiddenInCombinedWithoutCache');
        $entryShowing = $this->createEntry('showing', $magazine, $contentCreator, body: 'some text #wanted');
        $entryHidden = $this->createEntry('hidden', $magazine, $contentCreator, body: 'some text #notWanted');
        $entryCommentShowing = $this->createEntryComment('some text #wanted', $entryShowing, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notWanted', $entryShowing, $contentCreator);
        $postShowing = $this->createPost('some text #wanted', $magazine, $contentCreator);
        $postHidden = $this->createPost('some text #notWanted', $magazine, $contentCreator);
        $postCommentShowing = $this->createPostComment('some text #wanted', $postShowing, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notWanted', $postShowing, $contentCreator);
        $this->setContentTime($entryHidden, $entryShowing, 2);
        $this->setContentTime($entryCommentShowing, $entryShowing, 4);
        $this->setContentTime($entryCommentHidden, $entryShowing, 6);
        $this->setContentTime($postShowing, $entryShowing, 8);
        $this->setContentTime($postHidden, $entryShowing, 10);
        $this->setContentTime($postCommentShowing, $entryShowing, 12);
        $this->setContentTime($postCommentHidden, $entryShowing, 14);

        $user->follow($contentCreator);
        $this->magazineManager->subscribe($magazine, $user);
        $this->tagManager->block($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->includeBoosts = true;
        $criteria->perPage = 5;

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertInstanceOf(Entry::class, $result[0]);
        self::assertSame($entryShowing->getId(), $result[0]->getId());
        self::assertInstanceOf(EntryComment::class, $result[1]);
        self::assertSame($entryCommentShowing->getId(), $result[1]->getId());
        self::assertInstanceOf(Post::class, $result[2]);
        self::assertSame($postShowing->getId(), $result[2]->getId());
        self::assertInstanceOf(PostComment::class, $result[3]);
        self::assertSame($postCommentShowing->getId(), $result[3]->getId());
        self::assertCount(4, $result);
    }

    public function testBlockedHashtagIsHiddenInEntryComments()
    {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('testBlockedHashtagIsHiddenInEntryComments');
        $entry = $this->createEntry('something', $magazine, $contentCreator, body: 'some text');
        $commentShowing = $this->createEntryComment('some text #wanted', $entry, $contentCreator);
        $commentHidden = $this->createEntryComment('some text #notWanted', $entry, $contentCreator);

        $this->tagManager->block($user, $tag);

        $criteria = new EntryCommentPageView(1, $this->security);
        $criteria->showSortOption(Criteria::SORT_OLD);
        $criteria->entry = $entry;

        $fanta = $this->entryCommentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertSame($commentShowing->getId(), $result[0]->getId());
        self::assertCount(1, $result);
    }

    public function testBlockedHashtagIsHiddenInPostComments()
    {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('testBlockedHashtagIsHiddenInPostComments');
        $post = $this->createPost('something', $magazine, $contentCreator);
        $commentShowing = $this->createPostComment('some text #wanted', $post, $contentCreator);
        $commentHidden = $this->createPostComment('some text #notWanted', $post, $contentCreator);

        $this->tagManager->block($user, $tag);

        $criteria = new PostCommentPageView(1, $this->security);
        $criteria->showSortOption(Criteria::SORT_OLD);
        $criteria->post = $post;

        $fanta = $this->postCommentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertSame($commentShowing->getId(), $result[0]->getId());
        self::assertCount(1, $result);
    }

    public function testBlockedHashtagStillShowsOwnContentWithCache()
    {
        $user = $this->getUserByUsername('John Doe');
        $someoneElse = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('testBlockedHashtagStillShowsOwnContentWithCache');
        $entry = $this->createEntry('something', $magazine, $someoneElse, body: 'something #notWanted');
        $post = $this->createPost('something #notWanted', $magazine, $someoneElse);

        $entryShowing = $this->createEntry('something', $magazine, $user, body: 'something #notWanted');
        $entryCommentShowing = $this->createEntryComment('some text #notWanted', $entry, $user);
        $postShowing = $this->createPost('something #notWanted', $magazine, $user);
        $postCommentShowing = $this->createPostComment('some text #notWanted', $post, $user);

        $this->magazineManager->subscribe($magazine, $user);
        $this->tagManager->block($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->includeBoosts = true;
        $criteria->perPage = 5;
        $criteria->fetchCachedItems($this->sqlHelpers, $user);

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        $gotEntry = false;
        $gotEntryComment = false;
        $gotPost = false;
        $gotPostComment = false;
        foreach ($result as $item) {
            match (true) {
                $item instanceof Entry => $gotEntry = $item->getId() === $entryShowing->getId(),
                $item instanceof EntryComment => $gotEntryComment = $item->getId() === $entryCommentShowing->getId(),
                $item instanceof Post => $gotPost = $item->getId() === $postShowing->getId(),
                $item instanceof PostComment => $gotPostComment = $item->getId() === $postCommentShowing->getId(),
            };
        }
        self::assertTrue($gotEntry);
        self::assertTrue($gotEntryComment);
        self::assertTrue($gotPost);
        self::assertTrue($gotPostComment);
    }

    public function testBlockedHashtagStillShowsOwnContentWithoutCache()
    {
        $user = $this->getUserByUsername('John Doe');
        $someoneElse = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('testBlockedHashtagStillShowsOwnContentWithoutCache');
        $entry = $this->createEntry('something', $magazine, $someoneElse, body: 'something #notWanted');
        $post = $this->createPost('something #notWanted', $magazine, $someoneElse);

        $entryShowing = $this->createEntry('something', $magazine, $user, body: 'something #notWanted');
        $entryCommentShowing = $this->createEntryComment('some text #notWanted', $entry, $user);
        $postShowing = $this->createPost('something #notWanted', $magazine, $user);
        $postCommentShowing = $this->createPostComment('some text #notWanted', $post, $user);

        $this->magazineManager->subscribe($magazine, $user);
        $this->tagManager->block($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->includeBoosts = true;
        $criteria->perPage = 5;

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        $gotEntry = false;
        $gotEntryComment = false;
        $gotPost = false;
        $gotPostComment = false;
        foreach ($result as $item) {
            match (true) {
                $item instanceof Entry => $gotEntry = $item->getId() === $entryShowing->getId(),
                $item instanceof EntryComment => $gotEntryComment = $item->getId() === $entryCommentShowing->getId(),
                $item instanceof Post => $gotPost = $item->getId() === $postShowing->getId(),
                $item instanceof PostComment => $gotPostComment = $item->getId() === $postCommentShowing->getId(),
            };
        }
        self::assertTrue($gotEntry);
        self::assertTrue($gotEntryComment);
        self::assertTrue($gotPost);
        self::assertTrue($gotPostComment);
    }

    public function testAuthorCanSeeEntryWithBockedHashtag()
    {
        $user = $this->getUserByUsername('John Doe');
        $someoneElse = $this->getUserByUsername('poster');
        $magazine = $this->getMagazineByName('testAuthorCanSeeEntryWithBockedHashtag');
        $tag = $this->getHashtag('notWanted');

        $entryShowing = $this->createEntry('something', $magazine, $user, body: 'something #notWanted');
        $entryHidden = $this->createEntry('something', $magazine, $someoneElse, body: 'something #notWanted');

        $this->magazineManager->subscribe($magazine, $user);
        $this->tagManager->block($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_THREADS)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->perPage = 3;

        $fanta = $this->entryRepository->findByCriteria($criteria, $user);
        $results = $fanta->getCurrentPageResults();

        self::assertCount(1, $results);
        self::assertSame($entryShowing->getId(), $results[0]->getId());
    }

    public function testAuthorCanSeeEntryCommentWithBockedHashtag()
    {
        $user = $this->getUserByUsername('John Doe');
        $someoneElse = $this->getUserByUsername('poster');
        $magazine = $this->getMagazineByName('testAuthorCanSeeEntryCommentWithBockedHashtag');
        $entry = $this->createEntry('parent', $magazine, $user, body: 'parent');
        $tag = $this->getHashtag('notWanted');

        $commentShowing = $this->createEntryComment('some text #notWanted', $entry, $user);
        $commentHidden = $this->createEntryComment('some text #notWanted', $entry, $someoneElse);

        $this->tagManager->block($user, $tag);

        $criteria = new EntryCommentPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_THREADS)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->perPage = 3;

        $fanta = $this->entryCommentRepository->findByCriteria($criteria, $user);
        $results = $fanta->getCurrentPageResults();

        self::assertCount(1, $results);
        self::assertSame($commentShowing->getId(), $results[0]->getId());
    }

    public function testAuthorCanSeePostWithBockedHashtag()
    {
        $user = $this->getUserByUsername('John Doe');
        $someoneElse = $this->getUserByUsername('poster');
        $magazine = $this->getMagazineByName('testAuthorCanSeePostWithBockedHashtag');
        $tag = $this->getHashtag('notWanted');

        $postShowing = $this->createPost('something #notWanted', $magazine, $user);
        $postHidden = $this->createEntry('something #notWanted', $magazine, $someoneElse);

        $this->magazineManager->subscribe($magazine, $user);
        $this->tagManager->block($user, $tag);

        $criteria = new PostPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_MICROBLOG)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->perPage = 3;

        $fanta = $this->postRepository->findByCriteria($criteria, $user);
        $results = $fanta->getCurrentPageResults();

        self::assertCount(1, $results);
        self::assertSame($postShowing->getId(), $results[0]->getId());
    }

    public function testAuthorCanSeePostCommentWithBockedHashtag()
    {
        $user = $this->getUserByUsername('John Doe');
        $someoneElse = $this->getUserByUsername('poster');
        $magazine = $this->getMagazineByName('testAuthorCanSeePostCommentWithBockedHashtag');
        $post = $this->createPost('parent', $magazine, $user);
        $tag = $this->getHashtag('notWanted');

        $commentShowing = $this->createPostComment('something #notWanted', $post, $user);
        $commentHidden = $this->createPostComment('something #notWanted', $post, $someoneElse);

        $this->magazineManager->subscribe($magazine, $user);
        $this->tagManager->block($user, $tag);

        $criteria = new PostCommentPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_MICROBLOG)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->perPage = 3;

        $fanta = $this->postCommentRepository->findByCriteria($criteria, $user);
        $results = $fanta->getCurrentPageResults();

        self::assertCount(1, $results);
        self::assertSame($commentShowing->getId(), $results[0]->getId());
    }
}
