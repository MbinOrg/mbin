<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Comment\Moderate;

use App\DTO\ModeratorDto;
use App\Tests\WebTestCase;

class EntryCommentSetAdultApiTest extends WebTestCase
{
    public function testApiCannotSetCommentAdultAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/adult/true");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotSetCommentAdultWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $user2 = $this->getUserByUsername('user2');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $entry = $this->getEntryByTitle('an entry', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/adult/true", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonModCannotSetCommentAdult(): void
    {
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry_comment:set_adult');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/adult/true", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanSetCommentAdult(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $user2 = $this->getUserByUsername('other');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $entry = $this->getEntryByTitle('an entry', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry_comment:set_adult');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/adult/true", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertTrue($jsonData['isAdult']);
    }

    public function testApiCannotUnsetCommentAdultAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $entityManager = $this->entityManager;
        $comment->isAdult = true;
        $entityManager->persist($comment);
        $entityManager->flush();

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/adult/false");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUnsetCommentAdultWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $user2 = $this->getUserByUsername('user2');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $entry = $this->getEntryByTitle('an entry', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        $entityManager = $this->entityManager;
        $comment->isAdult = true;
        $entityManager->persist($comment);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/adult/false", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonModCannotUnsetCommentAdult(): void
    {
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $entityManager = $this->entityManager;
        $comment->isAdult = true;
        $entityManager->persist($comment);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry_comment:set_adult');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/adult/false", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUnsetCommentAdult(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByName('acme');
        $user2 = $this->getUserByUsername('other');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $entry = $this->getEntryByTitle('an entry', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        $entityManager = $this->entityManager;
        $comment->isAdult = true;
        $entityManager->persist($comment);
        $entityManager->flush();

        $commentRepository = $this->entryCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry_comment:set_adult');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/comment/{$comment->getId()}/adult/false", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertFalse($jsonData['isAdult']);

        $comment = $commentRepository->find($comment->getId());
        self::assertFalse($comment->isAdult);
    }
}
