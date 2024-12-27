<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Comment;

use App\Tests\WebTestCase;

class DomainEntryCommentRetrieveApiTest extends WebTestCase
{
    public function testApiCanGetDomainEntryCommentsAnonymous(): void
    {
        $this->getEntryByTitle('an entry', body: 'test');
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $entry = $this->getEntryByTitle('another entry', url: 'https://google.com', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry);
        $domain = $entry->domain;

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertEquals('test comment', $jsonData['items'][0]['body']);
        self::assertIsArray($jsonData['items'][0]['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['magazine']);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazine']['magazineId']);
        self::assertSame(0, $jsonData['items'][0]['childCount']);
        self::assertIsArray($jsonData['items'][0]['children']);
        self::assertEmpty($jsonData['items'][0]['children']);
        self::assertSame($comment->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame($entry->getId(), $jsonData['items'][0]['entryId']);
    }

    public function testApiCanGetDomainEntryComments(): void
    {
        $this->getEntryByTitle('an entry', body: 'test');
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $entry = $this->getEntryByTitle('another entry', url: 'https://google.com', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry);
        $domain = $entry->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertEquals('test comment', $jsonData['items'][0]['body']);
        self::assertIsArray($jsonData['items'][0]['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['magazine']);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazine']['magazineId']);
        self::assertSame(0, $jsonData['items'][0]['childCount']);
        self::assertIsArray($jsonData['items'][0]['children']);
        self::assertEmpty($jsonData['items'][0]['children']);
        self::assertSame($comment->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame($entry->getId(), $jsonData['items'][0]['entryId']);
    }

    public function testApiCanGetDomainEntryCommentsDepth(): void
    {
        $this->getEntryByTitle('an entry', body: 'test');
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $entry = $this->getEntryByTitle('another entry', url: 'https://google.com', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry);
        $nested1 = $this->createEntryComment('test comment nested 1', $entry, parent: $comment);
        $nested2 = $this->createEntryComment('test comment nested 2', $entry, parent: $nested1);
        $nested3 = $this->createEntryComment('test comment nested 3', $entry, parent: $nested2);
        $domain = $entry->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments?d=2", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertEquals('test comment', $jsonData['items'][0]['body']);
        self::assertIsArray($jsonData['items'][0]['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['magazine']);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazine']['magazineId']);
        self::assertSame(3, $jsonData['items'][0]['childCount']);
        self::assertIsArray($jsonData['items'][0]['children']);
        self::assertCount(1, $jsonData['items'][0]['children']);
        $child = $jsonData['items'][0]['children'][0];
        self::assertIsArray($child);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $child);
        self::assertSame(2, $child['childCount']);
        self::assertIsArray($child['children']);
        self::assertCount(1, $child['children']);
        self::assertIsArray($child['children'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $child);
        self::assertSame(1, $child['children'][0]['childCount']);
        self::assertIsArray($child['children'][0]['children']);
        self::assertEmpty($child['children'][0]['children']);
        self::assertSame($comment->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame($entry->getId(), $jsonData['items'][0]['entryId']);
    }

    public function testApiCanGetDomainEntryCommentsNewest(): void
    {
        $entry = $this->getEntryByTitle('entry', url: 'https://google.com');
        $first = $this->createEntryComment('first', $entry);
        $second = $this->createEntryComment('second', $entry);
        $third = $this->createEntryComment('third', $entry);
        $domain = $entry->domain;

        $first->createdAt = new \DateTimeImmutable('-1 hour');
        $second->createdAt = new \DateTimeImmutable('-1 second');
        $third->createdAt = new \DateTimeImmutable();

        $entityManager = $this->entityManager;
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->persist($third);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments?sort=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($third->getId(), $jsonData['items'][0]['commentId']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($first->getId(), $jsonData['items'][2]['commentId']);
    }

    public function testApiCanGetDomainEntryCommentsOldest(): void
    {
        $entry = $this->getEntryByTitle('entry', url: 'https://google.com');
        $first = $this->createEntryComment('first', $entry);
        $second = $this->createEntryComment('second', $entry);
        $third = $this->createEntryComment('third', $entry);
        $domain = $entry->domain;

        $first->createdAt = new \DateTimeImmutable('-1 hour');
        $second->createdAt = new \DateTimeImmutable('-1 second');
        $third->createdAt = new \DateTimeImmutable();

        $entityManager = $this->entityManager;
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->persist($third);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments?sort=oldest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($first->getId(), $jsonData['items'][0]['commentId']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($third->getId(), $jsonData['items'][2]['commentId']);
    }

    public function testApiCanGetDomainEntryCommentsActive(): void
    {
        $entry = $this->getEntryByTitle('entry', url: 'https://google.com');
        $first = $this->createEntryComment('first', $entry);
        $second = $this->createEntryComment('second', $entry);
        $third = $this->createEntryComment('third', $entry);
        $domain = $entry->domain;

        $first->lastActive = new \DateTime('-1 hour');
        $second->lastActive = new \DateTime('-1 second');
        $third->lastActive = new \DateTime();

        $entityManager = $this->entityManager;
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->persist($third);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments?sort=active", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($third->getId(), $jsonData['items'][0]['commentId']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($first->getId(), $jsonData['items'][2]['commentId']);
    }

    public function testApiCanGetDomainEntryCommentsTop(): void
    {
        $entry = $this->getEntryByTitle('entry', url: 'https://google.com');
        $first = $this->createEntryComment('first', $entry);
        $second = $this->createEntryComment('second', $entry);
        $third = $this->createEntryComment('third', $entry);
        $domain = $entry->domain;

        $favouriteManager = $this->favouriteManager;
        $favouriteManager->toggle($this->getUserByUsername('voter1'), $first);
        $favouriteManager->toggle($this->getUserByUsername('voter2'), $first);
        $favouriteManager->toggle($this->getUserByUsername('voter1'), $second);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments?sort=top", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($first->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame(2, $jsonData['items'][0]['favourites']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);
        self::assertSame(1, $jsonData['items'][1]['favourites']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($third->getId(), $jsonData['items'][2]['commentId']);
        self::assertSame(0, $jsonData['items'][2]['favourites']);
    }

    public function testApiCanGetDomainEntryCommentsHot(): void
    {
        $entry = $this->getEntryByTitle('entry', url: 'https://google.com');
        $first = $this->createEntryComment('first', $entry);
        $second = $this->createEntryComment('second', $entry);
        $third = $this->createEntryComment('third', $entry);
        $domain = $entry->domain;

        $voteManager = $this->voteManager;
        $voteManager->vote(1, $first, $this->getUserByUsername('voter1'), rateLimit: false);
        $voteManager->vote(1, $first, $this->getUserByUsername('voter2'), rateLimit: false);
        $voteManager->vote(1, $second, $this->getUserByUsername('voter1'), rateLimit: false);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments?sort=hot", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($first->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame(2, $jsonData['items'][0]['uv']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);
        self::assertSame(1, $jsonData['items'][1]['uv']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($third->getId(), $jsonData['items'][2]['commentId']);
        self::assertSame(0, $jsonData['items'][2]['uv']);
    }

    public function testApiCanGetDomainEntryCommentsWithUserVoteStatus(): void
    {
        $this->getEntryByTitle('an entry', body: 'test');
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $entry = $this->getEntryByTitle('another entry', url: 'https://google.com', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry);
        $domain = $entry->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/domain/{$domain->getId()}/comments", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($comment->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame($entry->getId(), $jsonData['items'][0]['entryId']);
        self::assertEquals('test comment', $jsonData['items'][0]['body']);
        self::assertIsArray($jsonData['items'][0]['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['magazine']);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazine']['magazineId']);
        self::assertIsArray($jsonData['items'][0]['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['user']);
        if (null !== $jsonData['items'][0]['image']) {
            self::assertStringContainsString('google.com', parse_url($jsonData['items'][0]['image']['sourceUrl'], PHP_URL_HOST));
        }
        self::assertEquals('en', $jsonData['items'][0]['lang']);
        self::assertEmpty($jsonData['items'][0]['tags']);
        self::assertSame(0, $jsonData['items'][0]['childCount']);
        self::assertIsArray($jsonData['items'][0]['children']);
        self::assertEmpty($jsonData['items'][0]['children']);
        self::assertSame(0, $jsonData['items'][0]['uv']);
        self::assertSame(0, $jsonData['items'][0]['dv']);
        self::assertSame(0, $jsonData['items'][0]['favourites']);
        self::assertFalse($jsonData['items'][0]['isFavourited']);
        self::assertSame(0, $jsonData['items'][0]['userVote']);
        self::assertFalse($jsonData['items'][0]['isAdult']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['items'][0]['createdAt'], 'createdAt date format invalid');
        self::assertNull($jsonData['items'][0]['editedAt']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['items'][0]['lastActive'], 'lastActive date format invalid');
        self::assertNull($jsonData['items'][0]['apId']);
    }
}
