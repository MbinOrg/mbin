<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Instance;

use App\Entity\Instance;
use App\Tests\WebTestCase;

class InstanceBlockApiTest extends WebTestCase
{
    public function testApiCannotGetListWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:read');

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotBlockWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:read');

        $this->client->request(
            'POST', '/api/users/instanceBlocks/block',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUnblockWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:read');

        $this->client->request(
            'POST', '/api/users/instanceBlocks/unblock',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotGlobalBlockWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->request(
            'POST', '/api/admin/instance/block',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreateAndListBlocks(): void
    {
        $user1 = $this->getUserByUsername('JohnDoe');
        $user2 = $this->getUserByUsername('JaneDoe');

        $this->prepareContent();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user1);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->jsonRequest(
            'POST', '/api/users/instanceBlocks/block',
            parameters: [
                'domains' => ['3.example.com'],
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceFederationApiTest::INSTANCE_DEFEDERATED_RESPONSE_KEYS, $jsonData);
        self::assertCount(1, $jsonData['instances']);
        self::assertSame('3.example.com', $jsonData['instances'][0]['domain']);

        $this->client->loginUser($user2);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->jsonRequest(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceFederationApiTest::INSTANCE_DEFEDERATED_RESPONSE_KEYS, $jsonData);
        self::assertCount(0, $jsonData['instances']);
    }

    public function testApiCanDeleteBlocks(): void
    {
        $user1 = $this->getUserByUsername('JohnDoe');

        $this->prepareContent();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user1);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->jsonRequest(
            'POST', '/api/users/instanceBlocks/block',
            parameters: [
                'domains' => ['3.example.com'],
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);
        $this->client->jsonRequest(
            'POST', '/api/users/instanceBlocks/block',
            parameters: [
                'domains' => ['2.example.com'],
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);

        $this->client->jsonRequest(
            'POST', '/api/users/instanceBlocks/unblock',
            parameters: [
                'domains' => ['3.example.com'],
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceFederationApiTest::INSTANCE_DEFEDERATED_RESPONSE_KEYS, $jsonData);
        self::assertCount(1, $jsonData['instances']);
        self::assertSame('2.example.com', $jsonData['instances'][0]['domain']);
    }

    public function testApiBlockInSearch()
    {
        $user1 = $this->getUserByUsername('JohnDoe');

        $this->prepareContent();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user1);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->jsonRequest(
            'POST', '/api/users/instanceBlocks/block',
            parameters: [
                'domains' => ['3.example.com'],
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);

        $this->client->request(
            'GET', '/api/search/v2?q=test',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $items = self::getJsonResponse($this->client)['items'];

        $this->checkContent($items, true);
    }

    public function testApiBlockInFeed()
    {
        $user1 = $this->getUserByUsername('JohnDoe');

        $this->prepareContent();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user1);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->jsonRequest(
            'POST', '/api/users/instanceBlocks/block',
            parameters: [
                'domains' => ['3.example.com'],
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);

        $this->client->request(
            'GET', '/api/combined?sort=newest',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $items = self::getJsonResponse($this->client)['items'];

        $this->checkContent($items, false);
    }

    public function testApiCanBlockGloballyBlocks(): void
    {
        $user1 = $this->getUserByUsername('JohnDoe');
        $user2 = $this->getUserByUsername('JaneDoe');
        $admin = $this->getUserByUsername('admin');
        $this->setAdmin($admin);

        $this->prepareContent();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($admin);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:federation:update user:profile:edit');

        $this->client->jsonRequest(
            'POST', '/api/admin/instance/block',
            parameters: [
                'domains' => ['2.example.com', '3.example.com'],
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(0, $jsonData['instances']);

        $this->client->loginUser($user1);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(2, $jsonData['instances']);
        $blockedDomains = array_map(fn ($instance) => $instance['domain'], $jsonData['instances']);
        self::assertContains('2.example.com', $blockedDomains);
        self::assertContains('3.example.com', $blockedDomains);

        $this->client->loginUser($user2);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->jsonRequest(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(2, $jsonData['instances']);
        $blockedDomains = array_map(fn ($instance) => $instance['domain'], $jsonData['instances']);
        self::assertContains('2.example.com', $blockedDomains);
        self::assertContains('3.example.com', $blockedDomains);
    }

    private function prepareContent(): void
    {
        $this->entityManager->persist(new Instance('1.example.com'));
        $this->entityManager->persist(new Instance('2.example.com'));
        $this->entityManager->persist(new Instance('3.example.com'));

        $remoteUser1 = $this->getUserByUsername('RemoA');
        $remoteUser1->apDomain = '1.example.com';
        $remoteUser1->apId = 'remo_a@1.example.com';
        $remoteUser1->apInboxUrl = 'https://1.example.com/inbox';
        $remoteUser1->apProfileId = 'https://1.example.com/user';
        $remoteUser2 = $this->getUserByUsername('RemoB');
        $remoteUser2->apDomain = '3.example.com';
        $remoteUser2->apId = 'remo_b@3.example.com';
        $remoteUser2->apInboxUrl = 'https://3.example.com/inbox';
        $remoteUser2->apProfileId = 'https://3.example.com/user';
        $this->entityManager->persist($remoteUser1);
        $this->entityManager->persist($remoteUser2);

        $magazine1 = $this->getMagazineByName('MagA');
        $magazine1->apDomain = '1.example.com';
        $magazine1->apId = 'mag@1.example.com';
        $magazine1->apInboxUrl = 'https://1.example.com/inbox';
        $magazine1->apProfileId = 'https://1.example.com/mag';
        $magazine2 = $this->getMagazineByName('MagB');
        $magazine2->apDomain = '3.example.com';
        $magazine2->apId = 'mag@3.example.com';
        $magazine2->apInboxUrl = 'https://3.example.com/inbox';
        $magazine2->apProfileId = 'https://3.example.com/mag';
        $this->entityManager->persist($magazine1);
        $this->entityManager->persist($magazine2);

        $entry1 = $this->createEntry('test visible', magazine: $magazine1, user: $remoteUser1);
        $entry2 = $this->createEntry('test invisible entry by magazine', magazine: $magazine2, user: $remoteUser1);
        $this->createEntry('test invisible entry by user', magazine: $magazine1, user: $remoteUser2);

        $post1 = $this->createPost('test visible', magazine: $magazine1, user: $remoteUser1);
        $post2 = $this->createPost('test invisible post by magazine', magazine: $magazine2, user: $remoteUser1);
        $this->createPost('test invisible post by user', magazine: $magazine1, user: $remoteUser2);

        $this->createEntryComment('test visible', entry: $entry1, user: $remoteUser1);
        $this->createEntryComment('test invisible entryComment by magazine', entry: $entry2, user: $remoteUser1);
        $this->createEntryComment('test invisible entryComment by user', entry: $entry1, user: $remoteUser2);

        $this->createPostComment('test visible', post: $post1, user: $remoteUser1);
        $this->createPostComment('test invisible postComment by magazine', post: $post2, user: $remoteUser1);
        $this->createPostComment('test invisible postComment by user', post: $post1, user: $remoteUser2);
    }

    private function checkContent(array $items, bool $hasComments): void
    {
        self::assertCount($hasComments ? 4 : 2, $items);

        foreach ($items as $item) {
            if (null !== $item['entry']) {
                self::assertSame('test visible', $item['entry']['title']);
            } elseif (null !== $item['post']) {
                self::assertSame('test visible', $item['post']['body']);
            } elseif (null !== $item['entryComment']) {
                self::assertSame('test visible', $item['entryComment']['body']);
            } elseif (null !== $item['postComment']) {
                self::assertSame('test visible', $item['postComment']['body']);
            } else {
                throw new \AssertionError('unreachable: '.json_encode($item));
            }
        }
    }
}
