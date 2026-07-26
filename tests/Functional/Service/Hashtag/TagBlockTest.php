<?php

namespace App\Tests\Functional\Service\Hashtag;

use App\PageView\EntryCommentPageView;
use App\PageView\EntryPageView;
use App\PageView\PostCommentPageView;
use App\Repository\Criteria;
use App\Tests\WebTestCase;

class TagBlockTest extends WebTestCase
{

    public function testBlock() {
        $user1 = $this->getUserByUsername('John Doe');
        $user2 = $this->getUserByUsername('Jane Doe');
        $tagNeutral = $this->getHashtag('abc');
        $tagBlocked = $this->getHashtag('def');

        $this->tagManager->block($user1, $tagBlocked);

        self::assertCount(1, $user1->blockedHashtags);
        self::assertSame($tagBlocked->tag, $user1->blockedHashtags[0]->hashtag->tag);
        self::assertCount(0, $user2->blockedHashtags);
    }

    public function testUnblock() {
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

    public function testBlockedHashtagIsHiddenInCombinedWithCache() {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('HashtagBlockTest');
        $entryShowing = $this->createEntry('showing', $magazine, $contentCreator, body: 'some text #wanted');
        $entryHidden = $this->createEntry('hidden', $magazine, $contentCreator, body: 'some text #notWanted');
        usleep(10000);
        $entryCommentShowing = $this->createEntryComment('some text #wanted', $entryShowing, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notWanted', $entryShowing, $contentCreator);
        usleep(10000);
        $postShowing = $this->createPost('some text #wanted', $magazine, $contentCreator);
        $postHidden = $this->createPost('some text #notWanted', $magazine, $contentCreator);
        usleep(10000);
        $postCommentShowing = $this->createPostComment('some text #wanted', $postShowing, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notWanted', $postShowing, $contentCreator);

        $user->follow($contentCreator);
        $this->tagManager->block($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_NEW);
        $criteria->magazine = $magazine;
        $criteria->includeBoosts = true;
        $criteria->perPage = 5;
        $criteria->fetchCachedItems($this->sqlHelpers, $user);

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertSame($entryShowing->getId(), $result[0]->getId());
        self::assertSame($entryCommentShowing->getId(), $result[1]->getId());
        self::assertSame($postShowing->getId(), $result[2]->getId());
        self::assertSame($postCommentShowing->getId(), $result[3]->getId());
        self::assertCount(4, $result);
    }

    public function testBlockedHashtagIsHiddenInCombinedWithoutCache() {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('HashtagBlockTest');
        $entryShowing = $this->createEntry('showing', $magazine, $contentCreator, body: 'some text #wanted');
        $entryHidden = $this->createEntry('hidden', $magazine, $contentCreator, body: 'some text #notWanted');
        usleep(10000);
        $entryCommentShowing = $this->createEntryComment('some text #wanted', $entryShowing, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notWanted', $entryShowing, $contentCreator);
        usleep(10000);
        $postShowing = $this->createPost('some text #wanted', $magazine, $contentCreator);
        $postHidden = $this->createPost('some text #notWanted', $magazine, $contentCreator);
        usleep(10000);
        $postCommentShowing = $this->createPostComment('some text #wanted', $postShowing, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notWanted', $postShowing, $contentCreator);

        $user->follow($contentCreator);
        $this->tagManager->block($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_NEW);
        $criteria->magazine = $magazine;
        $criteria->includeBoosts = true;
        $criteria->perPage = 5;

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertSame($entryShowing->getId(), $result[0]->getId());
        self::assertSame($entryCommentShowing->getId(), $result[1]->getId());
        self::assertSame($postShowing->getId(), $result[2]->getId());
        self::assertSame($postCommentShowing->getId(), $result[3]->getId());
        self::assertCount(4, $result);
    }

    public function testBlockedHashtagIsHiddenInEntryComments()
    {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('notWanted');

        $magazine = $this->getMagazineByName('HashtagBlockTest');
        $entry = $this->createEntry('something', $magazine, $contentCreator, body: 'some text');
        $commentShowing = $this->createEntryComment('some text #wanted', $entry, $contentCreator);
        $commentHidden = $this->createEntryComment('some text #notWanted', $entry, $contentCreator);

        $this->tagManager->block($user, $tag);

        $criteria = new EntryCommentPageView(1, $this->security);
        $criteria->showSortOption(Criteria::SORT_NEW);
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

        $magazine = $this->getMagazineByName('HashtagBlockTest');
        $post = $this->createPost('something', $magazine, $contentCreator);
        $commentShowing = $this->createPostComment('some text #wanted', $post, $contentCreator);
        $commentHidden = $this->createPostComment('some text #notWanted', $post, $contentCreator);

        $this->tagManager->block($user, $tag);

        $criteria = new PostCommentPageView(1, $this->security);
        $criteria->showSortOption(Criteria::SORT_NEW);
        $criteria->post = $post;

        $fanta = $this->postCommentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertSame($commentShowing->getId(), $result[0]->getId());
        self::assertCount(1, $result);
    }

}