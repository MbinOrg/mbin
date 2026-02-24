<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\ActivityPub;

use App\Tests\WebTestCase;

class GeneralAPTest extends WebTestCase
{
    public function testResponseToApProfile(): void
    {
        $user = $this->getUserByUsername('user');

        $this->client->request('GET', '/u/user', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json;profile=https://www.w3.org/ns/activitystreams',
        ]);

        self::assertResponseHeaderSame('Content-Type', 'application/activity+json');

        $this->client->request('GET', '/u/user', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
        ]);

        self::assertResponseHeaderSame('Content-Type', 'application/activity+json');

        $this->client->request('GET', '/u/user', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json ; profile="https://www.w3.org/ns/activitystreams"',
        ]);

        self::assertResponseHeaderSame('Content-Type', 'application/activity+json');
    }
}
