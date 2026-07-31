<?php
declare(strict_types=1);

namespace App\Tests\Functional\Service\Hashtag;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\PageView\EntryPageView;
use App\Repository\Criteria;
use App\Tests\WebTestCase;

class TagSubscriptionTest extends WebTestCase
{
    public function testSubscribe()
    {
        $user1 = $this->getUserByUsername('John Doe');
        $user2 = $this->getUserByUsername('Jane Doe');
        $tagNeutral = $this->getHashtag('abc');
        $tagBlocked = $this->getHashtag('def');

        $this->tagManager->subscribe($user1, $tagBlocked);

        self::assertCount(1, $user1->subscribedHashtags);
        self::assertSame($tagBlocked->tag, $user1->subscribedHashtags[0]->hashtag->tag);
        self::assertCount(0, $user2->subscribedHashtags);
    }

    public function testUnsubscribe()
    {
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

    public function testSubscribedHashtagIsIncludedInCombinedWithCache()
    {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('interesting');

        $magazine = $this->getMagazineByName('testSubscribedHashtagIsIncludedInCombinedWithCache');
        $entryShowing = $this->createEntry('showing', $magazine, $contentCreator, body: 'some text #interesting');
        $entryHidden = $this->createEntry('hidden', $magazine, $contentCreator, body: 'some text #notInteresting');
        $entryCommentNotShowing = $this->createEntryComment('some text #interesting', $entryShowing, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notInteresting', $entryShowing, $contentCreator);
        $postShowing = $this->createPost('some text #interesting', $magazine, $contentCreator);
        $postHidden = $this->createPost('some text #notInteresting', $magazine, $contentCreator);
        $postCommentNotShowing = $this->createPostComment('some text #interesting', $postShowing, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notInteresting', $postShowing, $contentCreator);
        $this->setContentTime($entryHidden, $entryShowing, 2);
        $this->setContentTime($entryCommentNotShowing, $entryShowing, 4);
        $this->setContentTime($entryCommentHidden, $entryShowing, 6);
        $this->setContentTime($postShowing, $entryShowing, 8);
        $this->setContentTime($postHidden, $entryShowing, 10);
        $this->setContentTime($postCommentNotShowing, $entryShowing, 12);
        $this->setContentTime($postCommentHidden, $entryShowing, 14);

        $this->tagManager->subscribe($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->includeBoosts = false;
        $criteria->perPage = 5;
        $criteria->fetchCachedItems($this->sqlHelpers, $user);

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertInstanceOf(Entry::class, $result[0]);
        self::assertSame($entryShowing->getId(), $result[0]->getId());
        self::assertInstanceOf(Post::class, $result[1]);
        self::assertSame($postShowing->getId(), $result[1]->getId());
        self::assertCount(2, $result);
    }

    public function testSubscribedHashtagIsIncludedInCombinedWithoutCache()
    {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('interesting');

        $magazine = $this->getMagazineByName('testSubscribedHashtagIsIncludedInCombinedWithoutCache');
        $entryShowing = $this->createEntry('showing', $magazine, $contentCreator, body: 'some text #interesting');
        $entryHidden = $this->createEntry('hidden', $magazine, $contentCreator, body: 'some text #notInteresting');
        $entryCommentNotShowing = $this->createEntryComment('some text #interesting', $entryShowing, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notInteresting', $entryShowing, $contentCreator);
        $postShowing = $this->createPost('some text #interesting', $magazine, $contentCreator);
        $postHidden = $this->createPost('some text #notInteresting', $magazine, $contentCreator);
        $postCommentNotShowing = $this->createPostComment('some text #interesting', $postShowing, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notInteresting', $postShowing, $contentCreator);
        $this->setContentTime($entryHidden, $entryShowing, 2);
        $this->setContentTime($entryCommentNotShowing, $entryShowing, 4);
        $this->setContentTime($entryCommentHidden, $entryShowing, 6);
        $this->setContentTime($postShowing, $entryShowing, 8);
        $this->setContentTime($postHidden, $entryShowing, 10);
        $this->setContentTime($postCommentNotShowing, $entryShowing, 12);
        $this->setContentTime($postCommentHidden, $entryShowing, 14);

        $this->tagManager->subscribe($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->includeBoosts = false;
        $criteria->perPage = 5;

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertInstanceOf(Entry::class, $result[0]);
        self::assertSame($entryShowing->getId(), $result[0]->getId());
        self::assertInstanceOf(Post::class, $result[1]);
        self::assertSame($postShowing->getId(), $result[1]->getId());
        self::assertCount(2, $result);
    }

    public function testSubscribedHashtagIsIncludedInCombinedComments() {
        $user = $this->getUserByUsername('John Doe');
        $contentCreator = $this->getUserByUsername('poster');
        $tag = $this->getHashtag('interesting');

        $magazine = $this->getMagazineByName('testSubscribedHashtagIsIncludedInCombinedComments');
        $entry = $this->createEntry('parent', $magazine, $contentCreator, body: 'some text');
        $entryCommentShowing = $this->createEntryComment('some text #interesting', $entry, $contentCreator);
        $entryCommentHidden = $this->createEntryComment('some text #notInteresting', $entry, $contentCreator);
        $post = $this->createPost('parent', $magazine, $contentCreator);
        $postCommentShowing = $this->createPostComment('some text #interesting', $post, $contentCreator);
        $postCommentHidden = $this->createPostComment('some text #notInteresting', $post, $contentCreator);
        $this->setContentTime($entryCommentShowing, $entry, 2);
        $this->setContentTime($entryCommentHidden, $entry, 4);
        $this->setContentTime($postCommentShowing, $entry, 6);
        $this->setContentTime($postCommentHidden, $entry, 8);

        $this->tagManager->subscribe($user, $tag);

        $criteria = new EntryPageView(1, $this->security)
            ->setContent(Criteria::CONTENT_COMBINED)
            ->showSortOption(Criteria::SORT_OLD);
        $criteria->subscribed = true;
        $criteria->includeCommentsWithSubscribedHashtag = true;
        $criteria->includeBoosts = true;
        $criteria->perPage = 5;

        $fanta = $this->contentRepository->findByCriteria($criteria, $user);
        $result = $fanta->getCurrentPageResults();

        self::assertInstanceOf(EntryComment::class, $result[0]);
        self::assertSame($entryCommentShowing->getId(), $result[0]->getId());
        self::assertInstanceOf(PostComment::class, $result[1]);
        self::assertSame($postCommentShowing->getId(), $result[1]->getId());
        self::assertCount(2, $result);
    }
}
