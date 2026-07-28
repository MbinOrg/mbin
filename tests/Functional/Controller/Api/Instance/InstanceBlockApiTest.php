<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Instance;

use App\DTO\UserDto;
use App\Entity\Instance;
use App\Tests\WebTestCase;

class InstanceBlockApiTest extends WebTestCase
{
    private array $authCodesUser1;
    private array $authCodesUser2;
    private array $authCodesAdmin;

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
        $this->setAdmin($testUser);
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->request(
            'POST', '/api/admin/instance/block',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotGlobalUnblockWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->setAdmin($testUser);
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');

        $this->client->request(
            'POST', '/api/admin/instance/unblock',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreateAndListBlocks(): void
    {
        $this->prepareContent();
        $codes = $this->authCodesUser1;

        $this->callBlock($codes, ['3.example.com']);

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceFederationApiTest::INSTANCE_DEFEDERATED_RESPONSE_KEYS, $jsonData);
        self::assertCount(1, $jsonData['instances']);
        self::assertSame('3.example.com', $jsonData['instances'][0]['domain']);

        $codes = $this->authCodesUser2;

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
        $this->prepareContent();
        $codes = $this->authCodesUser1;

        $this->callBlock($codes, ['2.example.com', '3.example.com']);
        $this->callUnblock($codes, ['3.example.com']);

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
        $this->prepareContent();
        $codes = $this->authCodesUser1;

        $this->callBlock($codes, ['3.example.com']);

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
        $this->prepareContent();
        $codes = $this->authCodesUser1;

        $this->callBlock($codes, ['3.example.com']);

        $this->client->request(
            'GET', '/api/combined?sort=newest',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();
        $items = self::getJsonResponse($this->client)['items'];

        $this->checkContent($items, false);
    }

    public function testApiCanCreateGlobalBlocks(): void
    {
        $this->prepareContent();

        $this->callBlock($this->authCodesAdmin, ['2.example.com', '3.example.com'], true);

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $this->authCodesAdmin['token_type'].' '.$this->authCodesAdmin['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(0, $jsonData['instances']);

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $this->authCodesUser1['token_type'].' '.$this->authCodesUser1['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(2, $jsonData['instances']);
        $blockedDomains = array_map(fn ($instance) => $instance['domain'], $jsonData['instances']);
        self::assertContains('2.example.com', $blockedDomains);
        self::assertContains('3.example.com', $blockedDomains);

        $this->client->jsonRequest(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $this->authCodesUser2['token_type'].' '.$this->authCodesUser2['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(2, $jsonData['instances']);
        $blockedDomains = array_map(fn ($instance) => $instance['domain'], $jsonData['instances']);
        self::assertContains('2.example.com', $blockedDomains);
        self::assertContains('3.example.com', $blockedDomains);
    }

    public function testApiCanCreateAndListGlobalBlocks(): void
    {
        $this->prepareContent();

        $this->callBlock($this->authCodesAdmin, ['2.example.com', '3.example.com'], true);

        $this->client->request(
            'GET', '/api/admin/instance/blocks',
            server: ['HTTP_AUTHORIZATION' => $this->authCodesAdmin['token_type'].' '.$this->authCodesAdmin['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(2, $jsonData['instances']);
        $blockedDomains = array_map(fn ($instance) => $instance['domain'], $jsonData['instances']);
        self::assertContains('2.example.com', $blockedDomains);
        self::assertContains('3.example.com', $blockedDomains);
    }

    public function testApiCanDeleteGlobalBlocks(): void
    {
        $this->prepareContent();

        $this->callBlock($this->authCodesUser1, ['2.example.com'], false);
        $this->callBlock($this->authCodesAdmin, ['2.example.com', '3.example.com'], true);
        $this->callBlock($this->authCodesUser2, ['3.example.com'], false);
        $this->callUnblock($this->authCodesAdmin, ['2.example.com', '3.example.com'], true);

        $this->client->request(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $this->authCodesUser1['token_type'].' '.$this->authCodesUser1['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(1, $jsonData['instances']);
        self::assertSame('2.example.com', $jsonData['instances'][0]['domain']);

        $this->client->jsonRequest(
            'GET', '/api/users/instanceBlocks',
            server: ['HTTP_AUTHORIZATION' => $this->authCodesUser2['token_type'].' '.$this->authCodesUser2['access_token']]
        );
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertCount(1, $jsonData['instances']);
        self::assertSame('3.example.com', $jsonData['instances'][0]['domain']);
    }

    public function testApiGlobalBlocksApplyToNewUsers(): void
    {
        $this->prepareContent();

        $this->callBlock($this->authCodesAdmin, ['2.example.com', '3.example.com'], true);

        $userDto = new UserDto();
        $userDto->username = 'FreshlyManufactured';
        $userDto->plainPassword = '123';
        $userDto->email = 'newuser@example.com';
        $user = $this->userManager->create($userDto);
        $this->client->loginUser($user);
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

        $user1 = $this->getUserByUsername('JohnDoe');
        $user2 = $this->getUserByUsername('JaneDoe');
        $admin = $this->getUserByUsername('admin');
        $this->setAdmin($admin);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user1);
        $this->authCodesUser1 = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');
        $this->client->loginUser($user2);
        $this->authCodesUser2 = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');
        $this->client->loginUser($admin);
        $this->authCodesAdmin = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit admin:federation:update');
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

    private function callBlock(array $codes, array $domains, bool $globally = false): void
    {
        if ($globally) {
            $url = '/api/admin/instance/block';
        } else {
            $url = '/api/users/instanceBlocks/block';
        }

        $this->client->jsonRequest(
            'POST', $url,
            parameters: [
                'domains' => $domains,
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);
    }

    private function callUnblock(array $codes, array $domains, bool $globally = false): void
    {
        if ($globally) {
            $url = '/api/admin/instance/unblock';
        } else {
            $url = '/api/users/instanceBlocks/unblock';
        }

        $this->client->jsonRequest(
            'POST', $url,
            parameters: [
                'domains' => $domains,
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(204);
    }
}
