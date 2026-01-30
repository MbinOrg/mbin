<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Instance;

use App\Tests\WebTestCase;

class InstanceRetrieveInfoApiTest extends WebTestCase
{
    public const INFO_KEYS = [
        'softwareName',
        'softwareVersion',
        'softwareRepository',
        'websiteDomain',
        'websiteContactEmail',
        'websiteTitle',
        'websiteOpenRegistrations',
        'websiteFederationEnabled',
        'websiteDefaultLang',
        'instanceModerators',
        'instanceAdmins',
    ];

    public const AP_USER_DEFAULT_KEYS = [
        'id',
        'type',
        'name',
        'preferredUsername',
        'inbox',
        'outbox',
        'url',
        'manuallyApprovesFollowers',
        'published',
        'following',
        'followers',
        'publicKey',
        'endpoints',
        'icon',
        'discoverable',
    ];

    public function testCanRetrieveInfoAnonymous(): void
    {
        $this->getUserByUsername('admin', isAdmin: true);
        $this->getUserByUsername('moderator', isModerator: true);
        $this->client->request('GET', '/api/info');

        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);
        self::assertArrayKeysMatch(self::INFO_KEYS, $jsonData);
        self::assertIsString($jsonData['softwareName']);
        self::assertIsString($jsonData['softwareVersion']);
        self::assertIsString($jsonData['softwareRepository']);
        self::assertIsString($jsonData['websiteDomain']);
        self::assertIsString($jsonData['websiteContactEmail']);
        self::assertIsString($jsonData['websiteTitle']);
        self::assertIsBool($jsonData['websiteOpenRegistrations']);
        self::assertIsBool($jsonData['websiteFederationEnabled']);
        self::assertIsString($jsonData['websiteDefaultLang']);
        self::assertIsArray($jsonData['instanceAdmins']);
        self::assertIsArray($jsonData['instanceModerators']);
        self::assertNotEmpty($jsonData['instanceAdmins']);
        self::assertNotEmpty($jsonData['instanceModerators']);
        self::assertArrayKeysMatch(self::AP_USER_DEFAULT_KEYS, $jsonData['instanceAdmins'][0]);
        self::assertArrayKeysMatch(self::AP_USER_DEFAULT_KEYS, $jsonData['instanceModerators'][0]);
    }
}
