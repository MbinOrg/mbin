<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry;

use App\DTO\UserSmallResponseDto;
use App\Tests\WebTestCase;

class EntriesActivityApiTest extends WebTestCase
{
    public const array ACTIVITIES_RESPONSE_DTO_KEYS = ['boosts', 'upvotes', 'downvotes'];

    public function testEmpty()
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for activites', user: $user, magazine: $magazine);

        $this->client->jsonRequest('GET', "/api/entry/{$entry->getId()}/activity");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ACTIVITIES_RESPONSE_DTO_KEYS, $jsonData);
        self::assertSame([], $jsonData['boosts']);
        self::assertSame([], $jsonData['upvotes']);
        self::assertSame(null, $jsonData['downvotes']);
    }

    public function testUpvotes()
    {
        $author = $this->getUserByUsername('userA');
        $user1 = $this->getUserByUsername('user1');
        $user2 = $this->getUserByUsername('user2');
        $this->getUserByUsername('user3');

        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for activites', user: $author, magazine: $magazine);

        $this->favouriteManager->toggle($user1, $entry);
        $this->favouriteManager->toggle($user2, $entry);

        $this->client->jsonRequest('GET', "/api/entry/{$entry->getId()}/activity");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ACTIVITIES_RESPONSE_DTO_KEYS, $jsonData);
        self::assertSame([], $jsonData['boosts']);
        self::assertSame(null, $jsonData['downvotes']);

        self::assertCount(2, $jsonData['upvotes']);
        self::assertTrue(array_all($jsonData['upvotes'], function ($u) use ($user1, $user2) {
            /* @var UserSmallResponseDto $u */
            return $u['userId'] === $user1->getId() || $u['userId'] === $user2->getId();
        }), serialize($jsonData['upvotes']));
    }

    public function testBoosts()
    {
        $author = $this->getUserByUsername('userA');
        $user1 = $this->getUserByUsername('user1');
        $user2 = $this->getUserByUsername('user2');
        $this->getUserByUsername('user3');

        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for activites', user: $author, magazine: $magazine);

        $this->voteManager->upvote($entry, $user1);
        $this->voteManager->upvote($entry, $user2);

        $this->client->jsonRequest('GET', "/api/entry/{$entry->getId()}/activity");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ACTIVITIES_RESPONSE_DTO_KEYS, $jsonData);
        self::assertSame([], $jsonData['upvotes']);
        self::assertSame(null, $jsonData['downvotes']);

        self::assertCount(2, $jsonData['boosts']);
        self::assertTrue(array_all($jsonData['boosts'], function ($u) use ($user1, $user2) {
            /* @var UserSmallResponseDto $u */
            return $u['userId'] === $user1->getId() || $u['userId'] === $user2->getId();
        }), serialize($jsonData['boosts']));
    }
}
