<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\WebTestCase;

class WebfingerControllerTest extends WebTestCase
{
    public function testInstanceActor(): void
    {
        $domain = $this->settingsManager->get('KBIN_DOMAIN');
        $resource = "acct:$domain@$domain";
        $resourceUrlEncoded = urlencode($resource);
        $this->client->request('GET', "https://$domain/.well-known/webfinger?resource=$resourceUrlEncoded");
        self::assertResponseIsSuccessful();
        $jsonContent = self::getJsonResponse($this->client);
        self::assertResponseIsSuccessful();

        self::assertArrayHasKey('subject', $jsonContent);
        self::assertEquals($resource, $jsonContent['subject']);
        self::assertArrayHasKey('links', $jsonContent);
        self::assertNotEmpty($jsonContent['links']);
        $instanceActor = $jsonContent['links'][0];
        self::assertArrayKeysMatch(['rel', 'href', 'type'], $instanceActor);

        $this->client->request('GET', $instanceActor['href']);

        self::assertResponseIsSuccessful();
        $jsonContent = self::getJsonResponse($this->client);
        self::assertNotNull($jsonContent);
        $keys = ['id', 'type', 'preferredUsername', 'publicKey', 'name', 'manuallyApprovesFollowers'];
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $jsonContent);
        }
        self::assertEquals($instanceActor['href'], $jsonContent['id']);
        self::assertEquals('Application', $jsonContent['type']);
        self::assertEquals($domain, $jsonContent['preferredUsername']);
        self::assertTrue($jsonContent['manuallyApprovesFollowers']);
        self::assertNotEmpty($jsonContent['publicKey']);
    }
}
