<?php

namespace App\Tests\Functional\Service\Hashtag;

use App\PageView\EntryCommentPageView;
use App\PageView\EntryPageView;
use App\PageView\PostCommentPageView;
use App\Repository\Criteria;
use App\Tests\WebTestCase;

class TagSubscriptionTest extends WebTestCase
{

    public function testSubscribe() {
        $user1 = $this->getUserByUsername('John Doe');
        $user2 = $this->getUserByUsername('Jane Doe');
        $tagNeutral = $this->getHashtag('abc');
        $tagBlocked = $this->getHashtag('def');

        $this->tagManager->subscribe($user1, $tagBlocked);

        self::assertCount(1, $user1->subscribedHashtags);
        self::assertSame($tagBlocked->tag, $user1->subscribedHashtags[0]->hashtag->tag);
        self::assertCount(0, $user2->subscribedHashtags);
    }

    public function testUnsubscribe() {
        $user1 = $this->getUserByUsername('John Doe');
        $user2 = $this->getUserByUsername('Jane Doe');
        $tag1 = $this->getHashtag('abc');
        $tag2 = $this->getHashtag('def');

        $this->tagManager->subscribe($user1, $tag1);
        $this->tagManager->subscribe($user1, $tag2);
        $this->tagManager->subscribe($user2, $tag1);

        $this->tagManager->unsubscribe($user1, $tag1);

        self::assertCount(1, $user1->subscribedHashtags);
        self::assertSame($tag2->tag, $user1->subscribedHashtags->first()->hashtag->tag);
        self::assertCount(1, $user2->subscribedHashtags);
        self::assertSame($tag1->tag, $user2->subscribedHashtags->first()->hashtag->tag);
    }

    public function testSubscribedHashtagIsIncludedInCombinedWithCache() {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('interesting');

        $magazine = $this->getMagazineByName('TagSubscriptionTest');
        $entryShowing = $this->createEntry('showing', $magazine, $contentCreator, body: 'some text #interesting');
        $entryHidden = $this->createEntry('hidden', $magazine, $contentCreator, body: 'some text #notInteresting');
        usleep(10000);
        $entryCommentShowing = $this->createEntryComment('some text #interesting', $entryShowing, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notInteresting', $entryShowing, $contentCreator);
        usleep(10000);
        $postShowing = $this->createPost('some text #interesting', $magazine, $contentCreator);
        $postHidden = $this->createPost('some text #notInteresting', $magazine, $contentCreator);
        usleep(10000);
        $postCommentShowing = $this->createPostComment('some text #interesting', $postShowing, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notInteresting', $postShowing, $contentCreator);

        $this->tagManager->subscribe($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_NEW);
        $criteria->subscribed = true;
        $criteria->includeBoosts = false;
        $criteria->perPage = 5;
        $criteria->fetchCachedItems($this->sqlHelpers, $user);

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertSame($entryShowing->getId(), $result[0]->getId());
        self::assertSame($postShowing->getId(), $result[1]->getId());
        self::assertCount(2, $result);
    }

    public function testSubscribedHashtagIsIncludedInCombinedWithoutCache() {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('interesting');

        $magazine = $this->getMagazineByName('TagSubscriptionTest');
        $entryShowing = $this->createEntry('showing', $magazine, $contentCreator, body: 'some text #interesting');
        $entryHidden = $this->createEntry('hidden', $magazine, $contentCreator, body: 'some text #notInteresting');
        usleep(10000);
        $entryCommentShowing = $this->createEntryComment('some text #interesting', $entryShowing, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notInteresting', $entryShowing, $contentCreator);
        usleep(10000);
        $postShowing = $this->createPost('some text #interesting', $magazine, $contentCreator);
        $postHidden = $this->createPost('some text #notInteresting', $magazine, $contentCreator);
        usleep(10000);
        $postCommentShowing = $this->createPostComment('some text #interesting', $postShowing, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notInteresting', $postShowing, $contentCreator);

        $this->tagManager->subscribe($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_NEW);
        $criteria->subscribed = true;
        $criteria->includeBoosts = false;
        $criteria->perPage = 5;

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertSame($entryShowing->getId(), $result[0]->getId());
        self::assertSame($postShowing->getId(), $result[1]->getId());
        self::assertCount(2, $result);
    }
}