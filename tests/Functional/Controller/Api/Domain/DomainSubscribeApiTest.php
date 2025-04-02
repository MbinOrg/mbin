<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Domain;

use App\Tests\WebTestCase;

class DomainSubscribeApiTest extends WebTestCase
{
    public function testApiCannotSubscribeToDomainAnonymous()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/subscribe");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotSubscribeToDomainWithoutScope()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/subscribe", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanSubscribeToDomain()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read domain:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/subscribe", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(DomainRetrieveApiTest::DOMAIN_RESPONSE_KEYS, $jsonData);
        self::assertEquals('example.com', $jsonData['name']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(1, $jsonData['subscriptionsCount']);
        self::assertTrue($jsonData['isUserSubscribed']);
        // Scope not granted so block flag not populated
        self::assertNull($jsonData['isBlockedByUser']);

        // Idempotent when called multiple times
        $this->client->request('PUT', "/api/domain/{$domain->getId()}/subscribe", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(DomainRetrieveApiTest::DOMAIN_RESPONSE_KEYS, $jsonData);
        self::assertEquals('example.com', $jsonData['name']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(1, $jsonData['subscriptionsCount']);
        self::assertTrue($jsonData['isUserSubscribed']);
        // Scope not granted so block flag not populated
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCannotUnsubscribeFromDomainAnonymous()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/unsubscribe");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUnsubscribeFromDomainWithoutScope()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/unsubscribe", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUnsubscribeFromDomain()
    {
        $user = $this->getUserByUsername('JohnDoe');
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;
        $manager = $this->domainManager;
        $manager->subscribe($domain, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read domain:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/unsubscribe", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(DomainRetrieveApiTest::DOMAIN_RESPONSE_KEYS, $jsonData);
        self::assertEquals('example.com', $jsonData['name']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['subscriptionsCount']);
        self::assertFalse($jsonData['isUserSubscribed']);
        // Scope not granted so block flag not populated
        self::assertNull($jsonData['isBlockedByUser']);

        // Idempotent when called multiple times
        $this->client->request('PUT', "/api/domain/{$domain->getId()}/unsubscribe", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(DomainRetrieveApiTest::DOMAIN_RESPONSE_KEYS, $jsonData);
        self::assertEquals('example.com', $jsonData['name']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['subscriptionsCount']);
        self::assertFalse($jsonData['isUserSubscribed']);
        // Scope not granted so block flag not populated
        self::assertNull($jsonData['isBlockedByUser']);
    }
}
