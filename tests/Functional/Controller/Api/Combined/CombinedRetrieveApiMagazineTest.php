<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Combined;

use App\Tests\WebTestCase;

class CombinedRetrieveApiMagazineTest extends WebTestCase
{
    public function testApiCanGetMagazineContentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('unrelated entry', body: 'test');
        $this->createEntryComment('up the ranking', $entry);
        $this->createPost('unrelated post');

        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $entry = $this->getEntryByTitle('another entry', url: 'https://wikipedia.com', magazine: $magazine);
        $this->voteManager->vote(1, $entry, $this->getUserByUsername('voter1'), rateLimit: false);
        $post = $this->createPost('a post', $magazine);
        $post->createdAt = new \DateTimeImmutable('now - 1min');
        $this->entityManager->flush();

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}/combined");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        $actualEntry = $jsonData['items'][0]['entry'];
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $actualEntry);
        self::assertEquals($entry->getId(), $actualEntry['entryId']);
        self::assertIsArray($actualEntry['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $actualEntry['magazine']);
        self::assertSame($magazine->getId(), $actualEntry['magazine']['magazineId']);
        self::assertEquals('link', $actualEntry['type']);
        self::assertSame(0, $actualEntry['numComments']);
        self::assertNull($actualEntry['crosspostedEntries']);

        self::assertIsArray($jsonData['items'][1]);
        $actualPost = $jsonData['items'][1]['post'];
        self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $actualPost);
        self::assertEquals($post->getId(), $actualPost['postId']);
        self::assertIsArray($actualPost['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $actualPost['magazine']);
        self::assertSame($magazine->getId(), $actualPost['magazine']['magazineId']);
    }

    public function testApiCanGetMagazineContent(): void
    {
        $entry = $this->getEntryByTitle('unrelated entry', body: 'test');
        $this->createEntryComment('up the ranking', $entry);
        $this->createPost('unrelated post');

        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $entry = $this->getEntryByTitle('another entry', url: 'https://wikipedia.com', magazine: $magazine);
        $this->voteManager->vote(1, $entry, $this->getUserByUsername('voter1'), rateLimit: false);
        $post = $this->createPost('a post', $magazine);
        $post->createdAt = new \DateTimeImmutable('now - 1min');
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}/combined", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        $actualEntry = $jsonData['items'][0]['entry'];
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $actualEntry);
        self::assertEquals($entry->getId(), $actualEntry['entryId']);
        self::assertIsArray($actualEntry['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $actualEntry['magazine']);
        self::assertSame($magazine->getId(), $actualEntry['magazine']['magazineId']);
        self::assertEquals('link', $actualEntry['type']);
        self::assertSame(0, $actualEntry['numComments']);
        self::assertNull($actualEntry['crosspostedEntries']);

        self::assertIsArray($jsonData['items'][1]);
        $actualPost = $jsonData['items'][1]['post'];
        self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $actualPost);
        self::assertEquals($post->getId(), $actualPost['postId']);
        self::assertIsArray($actualPost['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $actualPost['magazine']);
        self::assertSame($magazine->getId(), $actualPost['magazine']['magazineId']);
    }

    public function testApiCanGetMagazineContentPinnedFirst(): void
    {
        $voteManager = $this->voteManager;
        $voter = $this->getUserByUsername('voter');
        $first = $this->getEntryByTitle('an entry', body: 'test');
        $this->createEntryComment('up the ranking', $first);
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $second = $this->getEntryByTitle('another entry', url: 'https://wikipedia.com', magazine: $magazine);
        // Upvote and comment on $second so it should come first, but then pin $third so it actually comes first
        $voteManager->vote(1, $second, $voter, rateLimit: false);
        $this->createEntryComment('test', $second, $voter);
        $third = $this->getEntryByTitle('a pinned entry', url: 'https://wikipedia.com', magazine: $magazine);
        $third->createdAt = new \DateTimeImmutable('now - 1min');
        $this->entryManager->pin($third, null);
        $fourth = $this->createPost('a pinned post', $magazine);
        $this->postManager->pin($fourth);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}/combined", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertSame($fourth->getId(), $jsonData['items'][0]['post']['postId']);
        self::assertSame($third->getId(), $jsonData['items'][1]['entry']['entryId']);
        self::assertSame($second->getId(), $jsonData['items'][2]['entry']['entryId']);
    }

    public function testApiCanGetMagazineContentNewest(): void
    {
        $first = $this->getEntryByTitle('first', body: 'test');
        $magazine = $first->magazine;
        $second = $this->getEntryByTitle('second', url: 'https://wikipedia.com');
        $third = $this->createPost('third', $magazine);
        $forth = $this->createPost('forth', $magazine);
        $fifth = $this->getEntryByTitle('fifth', url: 'https://wikipedia.com');

        $first->createdAt = new \DateTimeImmutable('-1min');
        $second->createdAt = new \DateTimeImmutable('-2min');
        $third->createdAt = new \DateTimeImmutable('-3min');
        $forth->createdAt = new \DateTimeImmutable('-4min');
        $fifth->createdAt = new \DateTimeImmutable('-1hour');
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}/combined?sort=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(5, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(5, $jsonData['pagination']['count']);

        self::assertSame($first->getId(), $jsonData['items'][0]['entry']['entryId']);
        self::assertSame($second->getId(), $jsonData['items'][1]['entry']['entryId']);
        self::assertSame($third->getId(), $jsonData['items'][2]['post']['postId']);
        self::assertSame($forth->getId(), $jsonData['items'][3]['post']['postId']);
        self::assertSame($fifth->getId(), $jsonData['items'][4]['entry']['entryId']);
    }

    public function testApiCanGetMagazineContentOldest(): void
    {
        $first = $this->getEntryByTitle('first', body: 'test');
        $magazine = $first->magazine;
        $second = $this->getEntryByTitle('second', url: 'https://wikipedia.com');
        $third = $this->createPost('third', $magazine);
        $forth = $this->createPost('forth', $magazine);
        $fifth = $this->getEntryByTitle('fifth', url: 'https://wikipedia.com');

        $first->createdAt = new \DateTimeImmutable('-1hour');
        $second->createdAt = new \DateTimeImmutable('-4min');
        $third->createdAt = new \DateTimeImmutable('-3min');
        $forth->createdAt = new \DateTimeImmutable('-2min');
        $fifth->createdAt = new \DateTimeImmutable('-1min');
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}/combined?sort=oldest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(5, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(5, $jsonData['pagination']['count']);

        self::assertSame($first->getId(), $jsonData['items'][0]['entry']['entryId']);
        self::assertSame($second->getId(), $jsonData['items'][1]['entry']['entryId']);
        self::assertSame($third->getId(), $jsonData['items'][2]['post']['postId']);
        self::assertSame($forth->getId(), $jsonData['items'][3]['post']['postId']);
        self::assertSame($fifth->getId(), $jsonData['items'][4]['entry']['entryId']);
    }

    public function testApiCanGetMagazineContentCommented(): void
    {
        $first = $this->getEntryByTitle('first', body: 'test');
        $this->createEntryComment('comment 1', $first);
        $this->createEntryComment('comment 2', $first);
        $magazine = $first->magazine;
        $second = $this->createPost('second', $magazine);
        $this->createPostComment('comment 1', $second);
        $third = $this->getEntryByTitle('third', url: 'https://wikipedia.com');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}/combined?sort=commented", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertSame($first->getId(), $jsonData['items'][0]['entry']['entryId']);
        self::assertSame($second->getId(), $jsonData['items'][1]['post']['postId']);
        self::assertSame($third->getId(), $jsonData['items'][2]['entry']['entryId']);
    }

    public function testApiCanGetMagazineContentActive(): void
    {
        $first = $this->getEntryByTitle('first', body: 'test');
        $magazine = $first->magazine;
        $second = $this->getEntryByTitle('second', url: 'https://wikipedia.com');
        $third = $this->createPost('third', $magazine);

        $first->lastActive = new \DateTime('now');
        $second->lastActive = new \DateTime('-1sec');
        $third->lastActive = new \DateTime('-1hour');
        $this->entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}/combined?sort=active", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertSame($first->getId(), $jsonData['items'][0]['entry']['entryId']);
        self::assertSame($second->getId(), $jsonData['items'][1]['entry']['entryId']);
        self::assertSame($third->getId(), $jsonData['items'][2]['post']['postId']);
    }

    public function testApiCanGetMagazineContentTop(): void
    {
        $first = $this->getEntryByTitle('first', body: 'test');
        $magazine = $first->magazine;
        $second = $this->createPost('second', $magazine);
        $third = $this->getEntryByTitle('third', url: 'https://wikipedia.com');

        $voteManager = $this->voteManager;
        $voteManager->vote(1, $first, $this->getUserByUsername('voter1'), rateLimit: false);
        $voteManager->vote(1, $first, $this->getUserByUsername('voter2'), rateLimit: false);
        $voteManager->vote(1, $second, $this->getUserByUsername('voter1'), rateLimit: false);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}/combined?sort=top", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertSame($first->getId(), $jsonData['items'][0]['entry']['entryId']);
        self::assertSame($second->getId(), $jsonData['items'][1]['post']['postId']);
        self::assertSame($third->getId(), $jsonData['items'][2]['entry']['entryId']);
    }
}
