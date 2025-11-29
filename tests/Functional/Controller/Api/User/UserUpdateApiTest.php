<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User;

use App\DTO\UserSettingsDto;
use App\Entity\User;
use App\Enums\EDirectMessageSettings;
use App\Enums\EFrontContentOptions;
use App\Repository\Criteria;
use App\Tests\WebTestCase;

class UserUpdateApiTest extends WebTestCase
{
    public function testApiCannotUpdateCurrentUserProfileWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:profile:read');

        $this->client->jsonRequest(
            'PUT', '/api/users/profile',
            parameters: [
                'about' => 'Updated during test',
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateCurrentUserProfile(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:profile:edit user:profile:read');

        $this->client->request('GET', '/api/users/'.(string) $testUser->getId(), server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);
        self::assertSame($testUser->getId(), $jsonData['userId']);
        self::assertNull($jsonData['about']);

        $this->client->jsonRequest(
            'PUT', '/api/users/profile',
            parameters: [
                'about' => 'Updated during test',
            ],
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);
        self::assertSame($testUser->getId(), $jsonData['userId']);
        self::assertEquals('Updated during test', $jsonData['about']);

        $this->client->request('GET', '/api/users/'.(string) $testUser->getId(), server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);
        self::assertSame($testUser->getId(), $jsonData['userId']);
        self::assertEquals('Updated during test', $jsonData['about']);
    }

    public function testApiCannotUpdateCurrentUserSettingsWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:profile:read');

        $settings = (new UserSettingsDto(
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            User::HOMEPAGE_MOD,
            Criteria::SORT_HOT,
            Criteria::SORT_HOT,
            ['test'],
            ['en'],
            directMessageSetting: EDirectMessageSettings::Everyone->value,
            frontDefaultContent: EFrontContentOptions::Combined->value,
        ))->jsonSerialize();

        $this->client->jsonRequest(
            'PUT', '/api/users/settings',
            parameters: $settings,
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateCurrentUserSettings(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:profile:edit user:profile:read');

        $settings = (new UserSettingsDto(
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            User::HOMEPAGE_MOD,
            Criteria::SORT_NEW,
            Criteria::SORT_TOP,
            ['test'],
            ['en'],
            directMessageSetting: EDirectMessageSettings::FollowersOnly->value,
            frontDefaultContent: EFrontContentOptions::Threads->value,
        ))->jsonSerialize();

        $this->client->jsonRequest(
            'PUT', '/api/users/settings',
            parameters: $settings,
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(UserRetrieveApiTest::USER_SETTINGS_KEYS, $jsonData);

        self::assertFalse($jsonData['notifyOnNewEntry']);
        self::assertFalse($jsonData['notifyOnNewEntryReply']);
        self::assertFalse($jsonData['notifyOnNewEntryCommentReply']);
        self::assertFalse($jsonData['notifyOnNewPost']);
        self::assertFalse($jsonData['notifyOnNewPostReply']);
        self::assertFalse($jsonData['notifyOnNewPostCommentReply']);
        self::assertFalse($jsonData['hideAdult']);
        self::assertFalse($jsonData['showProfileSubscriptions']);
        self::assertFalse($jsonData['showProfileFollowings']);
        self::assertFalse($jsonData['addMentionsEntries']);
        self::assertFalse($jsonData['addMentionsPosts']);
        self::assertEquals(User::HOMEPAGE_MOD, $jsonData['homepage']);
        self::assertEquals(Criteria::SORT_NEW, $jsonData['frontDefaultSort']);
        self::assertEquals(Criteria::SORT_TOP, $jsonData['commentDefaultSort']);
        self::assertEquals(['test'], $jsonData['featuredMagazines']);
        self::assertEquals(['en'], $jsonData['preferredLanguages']);
        self::assertEquals(EDirectMessageSettings::FollowersOnly->value, $jsonData['directMessageSetting']);
        self::assertEquals(EFrontContentOptions::Threads->value, $jsonData['frontDefaultContent']);

        $this->client->request('GET', '/api/users/settings', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(UserRetrieveApiTest::USER_SETTINGS_KEYS, $jsonData);

        self::assertFalse($jsonData['notifyOnNewEntry']);
        self::assertFalse($jsonData['notifyOnNewEntryReply']);
        self::assertFalse($jsonData['notifyOnNewEntryCommentReply']);
        self::assertFalse($jsonData['notifyOnNewPost']);
        self::assertFalse($jsonData['notifyOnNewPostReply']);
        self::assertFalse($jsonData['notifyOnNewPostCommentReply']);
        self::assertFalse($jsonData['hideAdult']);
        self::assertFalse($jsonData['showProfileSubscriptions']);
        self::assertFalse($jsonData['showProfileFollowings']);
        self::assertFalse($jsonData['addMentionsEntries']);
        self::assertFalse($jsonData['addMentionsPosts']);
        self::assertEquals(User::HOMEPAGE_MOD, $jsonData['homepage']);
        self::assertEquals(Criteria::SORT_NEW, $jsonData['frontDefaultSort']);
        self::assertEquals(Criteria::SORT_TOP, $jsonData['commentDefaultSort']);
        self::assertEquals(['test'], $jsonData['featuredMagazines']);
        self::assertEquals(['en'], $jsonData['preferredLanguages']);
        self::assertEquals(EDirectMessageSettings::FollowersOnly->value, $jsonData['directMessageSetting']);
        self::assertEquals(EFrontContentOptions::Threads->value, $jsonData['frontDefaultContent']);
    }
}
