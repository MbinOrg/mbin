<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Admin;

use App\DTO\ModeratorDto;
use App\Event\Entry\EntryHasBeenSeenEvent;
use App\Tests\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class MagazineRetrieveStatsApiTest extends WebTestCase
{
    public const VIEW_STATS_KEYS = ['data'];
    public const STATS_BY_CONTENT_TYPE_KEYS = ['entry', 'post', 'entry_comment', 'post_comment'];

    public const COUNT_ITEM_KEYS = ['datetime', 'count'];
    public const VOTE_ITEM_KEYS = ['datetime', 'boost', 'down', 'up'];

    public function testApiCannotRetrieveMagazineStatsAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $this->client->request('GET', "/api/stats/magazine/{$magazine->getId()}/votes");

        self::assertResponseStatusCodeSame(401);
        $this->client->request('GET', "/api/stats/magazine/{$magazine->getId()}/content");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveMagazineStatsWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/stats/magazine/{$magazine->getId()}/votes", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', "/api/stats/magazine/{$magazine->getId()}/content", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotRetrieveMagazineStatsIfNotOwner(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $owner = $this->getUserByUsername('JaneDoe');
        $magazine = $this->getMagazineByName('test', $owner);
        $magazineManager = $this->magazineManager;
        $dto = new ModeratorDto($magazine);
        $dto->user = $this->getUserByUsername('JohnDoe');
        $dto->addedBy = $owner;
        $magazineManager->addModerator($dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:stats');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/stats/magazine/{$magazine->getId()}/votes", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', "/api/stats/magazine/{$magazine->getId()}/content", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveMagazineStats(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $user2 = $this->getUserByUsername('JohnDoe2');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');

        $entry = $this->getEntryByTitle('Stats test', body: 'This is gonna be a statistic', magazine: $magazine, user: $user);

        $requestStack = $this->requestStack;
        $requestStack->push(Request::create('/'));
        $dispatcher = $this->eventDispatcher;
        $dispatcher->dispatch(new EntryHasBeenSeenEvent($entry));

        $favouriteManager = $this->favouriteManager;
        $favourite = $favouriteManager->toggle($user, $entry);

        $voteManager = $this->voteManager;
        $vote = $voteManager->upvote($entry, $user);

        $entityManager = $this->entityManager;
        $entityManager->persist($favourite);
        $entityManager->persist($vote);
        $entityManager->flush();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:stats');
        $token = $codes['token_type'].' '.$codes['access_token'];

        // Start a day ago to avoid timezone issues when testing on machines with non-UTC timezones
        $startString = rawurlencode($entry->getCreatedAt()->add(\DateInterval::createFromDateString('-1 minute'))->format(\DateTimeImmutable::ATOM));
        $this->client->request('GET', "/api/stats/magazine/{$magazine->getId()}/votes?resolution=hour&start=$startString", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::STATS_BY_CONTENT_TYPE_KEYS, $jsonData);
        self::assertIsArray($jsonData['entry']);
        self::assertCount(1, $jsonData['entry']);
        self::assertIsArray($jsonData['entry_comment']);
        self::assertEmpty($jsonData['entry_comment']);
        self::assertIsArray($jsonData['post']);
        self::assertEmpty($jsonData['post']);
        self::assertIsArray($jsonData['post_comment']);
        self::assertEmpty($jsonData['post_comment']);
        self::assertArrayKeysMatch(self::VOTE_ITEM_KEYS, $jsonData['entry'][0]);
        self::assertSame(1, $jsonData['entry'][0]['up']);
        self::assertSame(0, $jsonData['entry'][0]['down']);
        self::assertSame(1, $jsonData['entry'][0]['boost']);

        $this->client->request('GET', "/api/stats/magazine/{$magazine->getId()}/content?resolution=hour&start=$startString", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::STATS_BY_CONTENT_TYPE_KEYS, $jsonData);
        self::assertIsInt($jsonData['entry']);
        self::assertIsInt($jsonData['entry_comment']);
        self::assertIsInt($jsonData['post']);
        self::assertIsInt($jsonData['post_comment']);
        self::assertSame(1, $jsonData['entry']);
    }
}
