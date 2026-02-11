<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User;

use App\Tests\WebTestCase;

class UserContentApiTest extends WebTestCase
{
    public function testCanGetUserContent()
    {
        $user = $this->getUserByUsername('JohnDoe');
        $dummyUser = $this->getUserByUsername('dummy');
        $magazine = $this->getMagazineByName('test');
        $entry1 = $this->createEntry('e 1', $magazine, $user);
        $entry2 = $this->createEntry('e 2', $magazine, $user);
        $entryDummy = $this->createEntry('dummy', $magazine, $dummyUser);
        $post1 = $this->createPost('p 1', $magazine, $user);
        $post2 = $this->createPost('p 2', $magazine, $user);
        $this->createPost('dummy', $magazine, $dummyUser);
        $comment1 = $this->createEntryComment('c 1', $entryDummy, $user);
        $comment2 = $this->createEntryComment('c 2', $entryDummy, $user);
        $this->createEntryComment('dummy', $entryDummy, $dummyUser);
        $reply1 = $this->createPostComment('r 1', $post1, $user);
        $reply2 = $this->createPostComment('r 2', $post1, $user);
        $this->createPostComment('dummy', $post1, $dummyUser);

        $this->client->request('GET', "/api/users/{$user->getId()}/content");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(8, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(8, $jsonData['pagination']['count']);

        self::assertTrue(array_all($jsonData['items'], function ($item) use ($entry1, $entry2, $post1, $post2, $comment1, $comment2, $reply1, $reply2) {
            return
                (null !== $item['entry'] && ($item['entry']['entryId'] === $entry1->getId() || $item['entry']['entryId'] === $entry2->getId()))
                || (null !== $item['post'] && ($item['post']['postId'] === $post1->getId() || $item['post']['postId'] === $post2->getId()))
                || (null !== $item['entryComment'] && ($item['entryComment']['commentId'] === $comment1->getId() || $item['entryComment']['commentId'] === $comment2->getId()))
                || (null !== $item['postComment'] && ($item['postComment']['commentId'] === $reply1->getId() || $item['postComment']['commentId'] === $reply2->getId()))
            ;
        }));
    }

    public function testCanGetUserBoosts()
    {
        $user = $this->getUserByUsername('JohnDoe');
        $dummyUser = $this->getUserByUsername('dummy');
        $magazine = $this->getMagazineByName('test');
        $entry1 = $this->createEntry('e 1', $magazine, $dummyUser);
        $entry2 = $this->createEntry('e 2', $magazine, $dummyUser);
        $entryDummy = $this->createEntry('dummy', $magazine, $dummyUser);
        $post1 = $this->createPost('p 1', $magazine, $dummyUser);
        $post2 = $this->createPost('p 2', $magazine, $dummyUser);
        $this->createPost('dummy', $magazine, $dummyUser);
        $comment1 = $this->createEntryComment('c 1', $entryDummy, $dummyUser);
        $comment2 = $this->createEntryComment('c 2', $entryDummy, $dummyUser);
        $this->createEntryComment('dummy', $entryDummy, $dummyUser);
        $reply1 = $this->createPostComment('r 1', $post1, $dummyUser);
        $reply2 = $this->createPostComment('r 2', $post1, $dummyUser);
        $this->createPostComment('dummy', $post1, $dummyUser);

        $this->voteManager->upvote($entry1, $user);
        $this->voteManager->upvote($entry2, $user);
        $this->voteManager->upvote($post1, $user);
        $this->voteManager->upvote($post2, $user);
        $this->voteManager->upvote($comment1, $user);
        $this->voteManager->upvote($comment2, $user);
        $this->voteManager->upvote($reply1, $user);
        $this->voteManager->upvote($reply2, $user);

        $this->client->request('GET', "/api/users/{$user->getId()}/boosts");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(8, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(8, $jsonData['pagination']['count']);

        self::assertTrue(array_all($jsonData['items'], function ($item) use ($entry1, $entry2, $post1, $post2, $comment1, $comment2, $reply1, $reply2) {
            return
                (null !== $item['entry'] && ($item['entry']['entryId'] === $entry1->getId() || $item['entry']['entryId'] === $entry2->getId()))
                || (null !== $item['post'] && ($item['post']['postId'] === $post1->getId() || $item['post']['postId'] === $post2->getId()))
                || (null !== $item['entryComment'] && ($item['entryComment']['commentId'] === $comment1->getId() || $item['entryComment']['commentId'] === $comment2->getId()))
                || (null !== $item['postComment'] && ($item['postComment']['commentId'] === $reply1->getId() || $item['postComment']['commentId'] === $reply2->getId()))
            ;
        }));
    }
}
