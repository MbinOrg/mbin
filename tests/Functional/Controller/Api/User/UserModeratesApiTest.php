<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User;

use App\DTO\ModeratorDto;
use App\Tests\WebTestCase;

class UserModeratesApiTest extends WebTestCase
{
    public function testApiCanRetrieveUserModeratedMagazines()
    {
        $owner = $this->getUserByUsername('JohnDoe');
        $user = $this->getUserByUsername('user');
        $magazine1 = $this->getMagazineByName('m 1');
        $magazine2 = $this->getMagazineByName('m 2');
        $this->getMagazineByName('dummy');

        $this->magazineManager->addModerator(new ModeratorDto($magazine1, $user, $owner));
        $this->magazineManager->addModerator(new ModeratorDto($magazine2, $user, $owner));

        $this->client->request('GET', "/api/users/{$user->getId()}/moderatedMagazines");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);

        self::assertTrue(array_all($jsonData['items'], function ($item) use ($magazine1, $magazine2) {
            return $item['magazineId'] === $magazine1->getId() || $item['magazineId'] === $magazine2->getId();
        }));
    }
}
