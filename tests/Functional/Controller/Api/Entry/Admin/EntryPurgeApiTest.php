<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Admin;

use App\Tests\WebTestCase;

class EntryPurgeApiTest extends WebTestCase
{
    public function testApiCannotPurgeArticleEntryAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for deletion', magazine: $magazine);

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotPurgeArticleEntryWithoutScope(): void
    {
        $user = $this->getUserByUsername('user', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for deletion', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonAdminCannotPurgeArticleEntry(): void
    {
        $otherUser = $this->getUserByUsername('somebody');
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for deletion', user: $otherUser, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanPurgeArticleEntry(): void
    {
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for deletion', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($admin);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testApiCannotPurgeLinkEntryAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test link', url: 'https://google.com', magazine: $magazine);

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotPurgeLinkEntryWithoutScope(): void
    {
        $user = $this->getUserByUsername('user', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test link', url: 'https://google.com', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonAdminCannotPurgeLinkEntry(): void
    {
        $otherUser = $this->getUserByUsername('somebody');
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test link', url: 'https://google.com', user: $otherUser, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanPurgeLinkEntry(): void
    {
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test link', url: 'https://google.com', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($admin);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testApiCannotPurgeImageEntryAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test image', image: $imageDto, magazine: $magazine);

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotPurgeImageEntryWithoutScope(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $user = $this->getUserByUsername('user', isAdmin: true);

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test image', image: $imageDto, user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonAdminCannotPurgeImageEntry(): void
    {
        $otherUser = $this->getUserByUsername('somebody');
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test image', image: $imageDto, user: $otherUser, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanPurgeImageEntry(): void
    {
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test image', image: $imageDto, user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($admin);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/entry/{$entry->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);
    }
}
