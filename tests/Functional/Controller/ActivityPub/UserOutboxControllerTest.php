<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\ActivityPub;

use App\DTO\MessageDto;
use App\Tests\ActivityPubTestCase;

class UserOutboxControllerTest extends ActivityPubTestCase
{
    public const array COLLECTION_KEYS = ['@context', 'first', 'id', 'type', 'totalItems'];
    public const array COLLECTION_ITEMS_KEYS = ['@context', 'type', 'id', 'totalItems', 'orderedItems', 'partOf'];

    public function setUp(): void
    {
        parent::setUp();

        $user = $this->getUserByUsername('apUser', addImage: false);
        $user2 = $this->getUserByUsername('apUser2', addImage: false);
        $magazine = $this->getMagazineByName('test-magazine');

        // create a message to test that it is not part of the outbox
        $dto = new MessageDto();
        $dto->body = 'this is a message';
        $thread = $this->messageManager->toThread($dto, $user, $user2);

        $entry = $this->createEntry('entry', $magazine, user: $user);
        $entryComment = $this->createEntryComment('comment', $entry, user: $user);
        $post = $this->createPost('post', $magazine, user: $user);
        $postComment = $this->createPostComment('comment', $post, user: $user);

        // upvote an entry to check that it is not part of the outbox
        $entryToLike = $this->getEntryByTitle('test entry 2');
        $this->favouriteManager->toggle($user, $entryToLike);

        // downvote an entry to check that it is not part of the outbox
        $entryToDislike = $this->getEntryByTitle('test entry 3');
        $this->voteManager->vote(-1, $entryToDislike, $user);

        // boost an entry to check that it is part of the outbox
        $entryToDislike = $this->getEntryByTitle('test entry 4');
        $this->voteManager->vote(1, $entryToDislike, $user);
    }

    public function testUserOutbox(): void
    {
        $this->client->request('GET', '/u/apUser/outbox', server: ['HTTP_ACCEPT' => 'application/activity+json']);
        self::assertResponseIsSuccessful();
        $json = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::COLLECTION_KEYS, $json);
        self::assertEquals('OrderedCollection', $json['type']);
        self::assertEquals(5, $json['totalItems']);

        $firstPage = $json['first'];

        $this->client->request('GET', $firstPage, server: ['HTTP_ACCEPT' => 'application/activity+json']);
        self::assertResponseIsSuccessful();
    }

    public function testUserOutboxPage1(): void
    {
        $this->client->request('GET', '/u/apUser/outbox?page=1', server: ['HTTP_ACCEPT' => 'application/activity+json']);
        self::assertResponseIsSuccessful();
        $json = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::COLLECTION_ITEMS_KEYS, $json);
        self::assertEquals(5, $json['totalItems']);
        self::assertCount(5, $json['orderedItems']);

        $entries = array_filter($json['orderedItems'], fn (array $createActivity) => 'Create' === $createActivity['type'] && 'Page' === $createActivity['object']['type']);
        self::assertCount(1, $entries);
        $entryComments = array_filter($json['orderedItems'], fn (array $createActivity) => 'Create' === $createActivity['type'] && 'Note' === $createActivity['object']['type'] && str_contains($createActivity['object']['inReplyTo'] ?? '', '/t/'));
        self::assertCount(1, $entryComments);
        $posts = array_filter($json['orderedItems'], fn (array $createActivity) => 'Create' === $createActivity['type'] && 'Note' === $createActivity['object']['type'] && null === $createActivity['object']['inReplyTo']);
        self::assertCount(1, $posts);
        $postComments = array_filter($json['orderedItems'], fn (array $createActivity) => 'Create' === $createActivity['type'] && 'Note' === $createActivity['object']['type'] && str_contains($createActivity['object']['inReplyTo'] ?? '', '/p/'));
        self::assertCount(1, $postComments);
        $boosts = array_filter($json['orderedItems'], fn (array $createActivity) => 'Announce' === $createActivity['type']);
        self::assertCount(1, $boosts);

        // the outbox should not contain ChatMessages, likes or dislikes
        $likes = array_filter($json['orderedItems'], fn (array $createActivity) => 'Like' === $createActivity['type']);
        self::assertCount(0, $likes);
        $dislikes = array_filter($json['orderedItems'], fn (array $createActivity) => 'Dislike' === $createActivity['type']);
        self::assertCount(0, $dislikes);
        $chatMessages = array_filter($json['orderedItems'], fn (array $createActivity) => 'Create' === $createActivity['type'] && 'ChatMessage' === $createActivity['object']['type']);
        self::assertCount(0, $chatMessages);

        $ids = array_map(fn (array $createActivity) => $createActivity['id'], $json['orderedItems']);

        $this->client->request('GET', '/u/apUser/outbox?page=1', server: ['HTTP_ACCEPT' => 'application/activity+json']);
        self::assertResponseIsSuccessful();
        $json = self::getJsonResponse($this->client);

        $ids2 = array_map(fn (array $createActivity) => $createActivity['id'], $json['orderedItems']);

        // check that the ids of the 'Create' activities are stable
        self::assertEquals($ids, $ids2);
    }
}
