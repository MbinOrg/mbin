<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Domain;

use App\Service\DomainManager;
use App\Tests\WebTestCase;

class DomainBlockApiTest extends WebTestCase
{
    public function testApiCannotBlockDomainAnonymous()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/block");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotBlockDomainWithoutScope()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/block", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanBlockDomain()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read domain:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/block", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(DomainRetrieveApiTest::DOMAIN_RESPONSE_KEYS, $jsonData);
        self::assertEquals('example.com', $jsonData['name']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['subscriptionsCount']);
        self::assertTrue($jsonData['isBlockedByUser']);
        // Scope not granted so subscribe flag not populated
        self::assertNull($jsonData['isUserSubscribed']);

        // Idempotent when called multiple times
        $this->client->request('PUT', "/api/domain/{$domain->getId()}/block", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(DomainRetrieveApiTest::DOMAIN_RESPONSE_KEYS, $jsonData);
        self::assertEquals('example.com', $jsonData['name']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['subscriptionsCount']);
        self::assertTrue($jsonData['isBlockedByUser']);
        // Scope not granted so subscribe flag not populated
        self::assertNull($jsonData['isUserSubscribed']);
    }

    public function testApiCannotUnblockDomainAnonymous()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/unblock");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUnblockDomainWithoutScope()
    {
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/unblock", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUnblockDomain()
    {
        $user = $this->getUserByUsername('JohnDoe');
        $domain = $this->getEntryByTitle('Test link to a domain', 'https://example.com')->domain;
        $manager = $this->getService(DomainManager::class);
        $manager->block($domain, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read domain:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/domain/{$domain->getId()}/unblock", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(DomainRetrieveApiTest::DOMAIN_RESPONSE_KEYS, $jsonData);
        self::assertEquals('example.com', $jsonData['name']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['subscriptionsCount']);
        self::assertFalse($jsonData['isBlockedByUser']);
        // Scope not granted so subscribe flag not populated
        self::assertNull($jsonData['isUserSubscribed']);

        // Idempotent when called multiple times
        $this->client->request('PUT', "/api/domain/{$domain->getId()}/unblock", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(DomainRetrieveApiTest::DOMAIN_RESPONSE_KEYS, $jsonData);
        self::assertEquals('example.com', $jsonData['name']);
        self::assertSame(1, $jsonData['entryCount']);
        self::assertSame(0, $jsonData['subscriptionsCount']);
        self::assertFalse($jsonData['isBlockedByUser']);
        // Scope not granted so subscribe flag not populated
        self::assertNull($jsonData['isUserSubscribed']);
    }
}
