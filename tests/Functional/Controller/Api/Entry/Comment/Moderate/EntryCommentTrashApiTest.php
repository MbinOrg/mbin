<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Comment\Moderate;

use App\DTO\ModeratorDto;
use App\Service\EntryCommentManager;
use App\Service\MagazineManager;
use App\Tests\WebTestCase;

class EntryCommentTrashApiTest extends WebTestCase
{
    public function testApiCannotTrashCommentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/trash");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotTrashCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $user2 = $this->getUserByUsername('user2');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $entry = $this->getEntryByTitle('an entry', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $magazineManager = $this->getService(MagazineManager::class);
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonModCannotTrashComment(): void
    {
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry_comment:trash');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanTrashComment(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $user2 = $this->getUserByUsername('other');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $entry = $this->getEntryByTitle('an entry', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $magazineManager = $this->getService(MagazineManager::class);
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry_comment:trash');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment->getId(), $jsonData['commentId']);
        self::assertSame('test comment', $jsonData['body']);
        self::assertSame('trashed', $jsonData['visibility']);
    }

    public function testApiCannotRestoreCommentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $entryCommentManager = $this->getService(EntryCommentManager::class);
        $entryCommentManager->trash($this->getUserByUsername('user'), $comment);

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/restore");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRestoreCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $entryCommentManager = $this->getService(EntryCommentManager::class);
        $entryCommentManager->trash($user, $comment);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/restore", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonModCannotRestoreComment(): void
    {
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $entryCommentManager = $this->getService(EntryCommentManager::class);
        $entryCommentManager->trash($user, $comment);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry_comment:trash');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/restore", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRestoreComment(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $user2 = $this->getUserByUsername('other');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $entry = $this->getEntryByTitle('an entry', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $magazineManager = $this->getService(MagazineManager::class);
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        $entryCommentManager = $this->getService(EntryCommentManager::class);
        $entryCommentManager->trash($user, $comment);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry_comment:trash');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/restore", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment->getId(), $jsonData['commentId']);
        self::assertSame('test comment', $jsonData['body']);
        self::assertSame('visible', $jsonData['visibility']);
    }
}
