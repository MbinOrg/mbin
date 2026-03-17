<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\ActivityPub;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class GeneralAPTest extends WebTestCase
{
    #[DataProvider('provideAcceptHeaders')]
    public function testResponseToApProfile(string $acceptHeader): void
    {
        $user = $this->getUserByUsername('user');

        $this->client->request('GET', '/u/user', [], [], [
            'HTTP_ACCEPT' => $acceptHeader,
        ]);

        self::assertResponseHeaderSame('Content-Type', 'application/activity+json');
    }

    public static function provideAcceptHeaders(): array
    {
        return [
            ['application/ld+json;profile=https://www.w3.org/ns/activitystreams'],
            ['application/ld+json;profile="https://www.w3.org/ns/activitystreams"'],
            ['application/ld+json ; profile="https://www.w3.org/ns/activitystreams"'],
            ['application/ld+json'],
            ['application/activity+json'],
            ['application/json'],
        ];
    }
}
