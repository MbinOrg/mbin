<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Post;

use App\Tests\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PostCreateApiTest extends WebTestCase
{
    public function testApiCannotCreatePostAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $postRequest = [
            'body' => 'This is a microblog',
            'lang' => 'en',
            'isAdult' => false,
        ];

        $this->client->jsonRequest('POST', "/api/magazine/{$magazine->getId()}/posts", parameters: $postRequest);
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotCreatePostWithoutScope(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $postRequest = [
            'body' => 'No scope post',
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('POST', "/api/magazine/{$magazine->getId()}/posts", parameters: $postRequest, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreatePost(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $postRequest = [
            'body' => 'This is a microblog #test @user',
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('POST', "/api/magazine/{$magazine->getId()}/posts", parameters: $postRequest, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(201);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $jsonData);
        self::assertNotNull($jsonData['postId']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertEquals('This is a microblog #test @user', $jsonData['body']);
        self::assertNull($jsonData['image']);
        self::assertEquals('en', $jsonData['lang']);
        self::assertIsArray($jsonData['tags']);
        self::assertSame(['test'], $jsonData['tags']);
        self::assertIsArray($jsonData['mentions']);
        self::assertSame(['@user'], $jsonData['mentions']);
        self::assertSame(0, $jsonData['comments']);
        self::assertSame(0, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        // No scope for seeing votes granted
        self::assertNull($jsonData['isFavourited']);
        self::assertNull($jsonData['userVote']);
        self::assertFalse($jsonData['isAdult']);
        self::assertFalse($jsonData['isPinned']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['createdAt'], 'createdAt date format invalid');
        self::assertNull($jsonData['editedAt']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['lastActive'], 'lastActive date format invalid');
        self::assertNull($jsonData['apId']);
        self::assertEquals('This-is-a-microblog-test-at-user', $jsonData['slug']);
    }

    public function testApiCannotCreateImagePostAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $postRequest = [
            'alt' => 'It\'s kibby!',
            'lang' => 'en',
            'isAdult' => false,
        ];

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        copy($this->kibbyPath, $this->kibbyPath.'.tmp');
        $image = new UploadedFile($this->kibbyPath.'.tmp', 'kibby_emoji.png', 'image/png');

        $this->client->request(
            'POST', "/api/magazine/{$magazine->getId()}/posts/image",
            parameters: $postRequest, files: ['uploadImage' => $image],
        );
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotCreateImagePostWithoutScope(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $postRequest = [
            'alt' => 'It\'s kibby!',
            'lang' => 'en',
            'isAdult' => false,
        ];

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        $tmpPath = bin2hex(random_bytes(32));
        copy($this->kibbyPath, $tmpPath.'.png');
        $image = new UploadedFile($tmpPath.'.png', 'kibby_emoji.png', 'image/png');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request(
            'POST', "/api/magazine/{$magazine->getId()}/posts/image",
            parameters: $postRequest, files: ['uploadImage' => $image],
            server: ['HTTP_AUTHORIZATION' => $token]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreateImagePost(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $postRequest = [
            'alt' => 'It\'s kibby!',
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        $tmpPath = bin2hex(random_bytes(32));
        copy($this->kibbyPath, $tmpPath.'.png');
        $image = new UploadedFile($tmpPath.'.png', 'kibby_emoji.png', 'image/png');

        $imageManager = $this->imageManager;
        $expectedPath = $imageManager->getFilePath($image->getFilename());

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request(
            'POST', "/api/magazine/{$magazine->getId()}/posts/image",
            parameters: $postRequest, files: ['uploadImage' => $image],
            server: ['HTTP_AUTHORIZATION' => $token]
        );
        self::assertResponseStatusCodeSame(201);
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $jsonData);
        self::assertNotNull($jsonData['postId']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertEquals('', $jsonData['body']);
        self::assertIsArray($jsonData['image']);
        self::assertArrayKeysMatch(self::IMAGE_KEYS, $jsonData['image']);
        self::assertStringContainsString($expectedPath, $jsonData['image']['filePath']);
        self::assertEquals('It\'s kibby!', $jsonData['image']['altText']);
        self::assertEquals('en', $jsonData['lang']);
        self::assertEmpty($jsonData['tags']);
        self::assertNull($jsonData['mentions']);
        self::assertSame(0, $jsonData['comments']);
        self::assertSame(0, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        // No scope for seeing votes granted
        self::assertNull($jsonData['isFavourited']);
        self::assertNull($jsonData['userVote']);
        self::assertFalse($jsonData['isAdult']);
        self::assertFalse($jsonData['isPinned']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['createdAt'], 'createdAt date format invalid');
        self::assertNull($jsonData['editedAt']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['lastActive'], 'lastActive date format invalid');
        self::assertNull($jsonData['apId']);
        self::assertEquals('acme-It-s-kibby', $jsonData['slug']);
    }

    public function testApiCannotCreatePostWithoutMagazine(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $invalidId = $magazine->getId() + 1;
        $postRequest = [
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('POST', "/api/magazine/{$invalidId}/posts", parameters: $postRequest, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', "/api/magazine/{$invalidId}/posts/image", parameters: $postRequest, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testApiCannotCreatePostWithoutBodyOrImage(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $postRequest = [
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('POST', "/api/magazine/{$magazine->getId()}/posts", parameters: $postRequest, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', "/api/magazine/{$magazine->getId()}/posts/image", parameters: $postRequest, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(400);
    }
}
