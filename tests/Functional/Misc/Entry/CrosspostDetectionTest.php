<?php

declare(strict_types=1);

namespace App\Tests\Functional\Misc\Entry;

use App\Entity\Entry;
use App\Tests\WebTestCase;

class CrosspostDetectionTest extends WebTestCase
{
    public function testCrosspostsNoCrosspost(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $magazine1 = $this->getMagazineByName('acme1');
        $entry1 = $this->createEntry('article 001', $magazine1, $user);
        sleep(1);
        $magazine2 = $this->getMagazineByName('acme2');
        $entry2 = $this->createEntry('article 002', $magazine2, $user);
        $this->entityManager->persist($entry1);
        $this->entityManager->persist($entry2);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/entries?sort=oldest');
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertSame(2, $jsonData['pagination']['count']);
        self::assertNull($jsonData['items'][0]['crosspostedEntries']);
        self::assertNull($jsonData['items'][1]['crosspostedEntries']);
    }

    public function testCrosspostsByTitle(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $magazine1 = $this->getMagazineByName('acme1');
        $entry1 = $this->createEntry('article 001', $magazine1, $user);
        sleep(1);
        $magazine2 = $this->getMagazineByName('acme2');
        $entry2 = $this->createEntry('article 001', $magazine2, $user);
        sleep(1);
        $magazine3 = $this->getMagazineByName('acme3');
        $entry3 = $this->createEntry('article 001', $magazine3, $user);
        sleep(1);
        $magazine4 = $this->getMagazineByName('acme4');
        $entry4 = $this->createEntry('article 002', $magazine4, $user);
        $this->entityManager->persist($entry1);
        $this->entityManager->persist($entry2);
        $this->entityManager->persist($entry3);
        $this->entityManager->persist($entry4);
        $this->entityManager->flush();

        $this->checkCrossposts([$entry1, $entry2, $entry3]);
        $this->checkCrossposts([$entry4]);
    }

    public function testCrosspostsByUrl(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $magazine1 = $this->getMagazineByName('acme1');
        $entry1 = $this->createEntry('article 001', $magazine1, $user, url: 'https://duckduckgo.com');
        sleep(1);
        $magazine2 = $this->getMagazineByName('acme2');
        $entry2 = $this->createEntry('article 001', $magazine2, $user, url: 'https://duckduckgo.com');
        sleep(1);
        $magazine3 = $this->getMagazineByName('acme3');
        $entry3 = $this->createEntry('article with url', $magazine3, $user, url: 'https://duckduckgo.com');
        sleep(1);
        $magazine4 = $this->getMagazineByName('acme4');
        $entry4 = $this->createEntry('article 001', $magazine4, $user, url: 'https://google.com');
        $this->entityManager->persist($entry1);
        $this->entityManager->persist($entry2);
        $this->entityManager->persist($entry3);
        $this->entityManager->persist($entry4);
        $this->entityManager->flush();

        $this->checkCrossposts([$entry1, $entry2, $entry3]);
        $this->checkCrossposts([$entry4]);
    }

    public function testCrosspostsByTitleWithImageFilter(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $img1 = $this->getKibbyImageDto();
        $img2 = $this->getKibbyFlippedImageDto();

        $magazine1 = $this->getMagazineByName('acme1');
        $entry1 = $this->createEntry('article 001', $magazine1, $user, imageDto: $img1);
        sleep(1);
        $magazine2 = $this->getMagazineByName('acme2');
        $entry2 = $this->createEntry('article 001', $magazine2, $user, imageDto: $img1);
        sleep(1);
        $magazine3 = $this->getMagazineByName('acme3');
        $entry3 = $this->createEntry('article 001', $magazine3, $user, imageDto: $img2);
        sleep(1);
        $magazine4 = $this->getMagazineByName('acme4');
        $entry4 = $this->createEntry('article 002', $magazine4, $user);
        $this->entityManager->persist($entry1);
        $this->entityManager->persist($entry2);
        $this->entityManager->persist($entry3);
        $this->entityManager->persist($entry4);
        $this->entityManager->flush();

        $this->checkCrossposts([$entry1, $entry2]);
        $this->checkCrossposts([$entry3]);
        $this->checkCrossposts([$entry4]);
    }

    /**
     * @param Entry[] $expectedEntries
     */
    private function checkCrossposts(array $expectedEntries): void
    {
        $this->client->request('GET', '/api/entries?sort=oldest');
        self::assertResponseIsSuccessful();

        foreach ($expectedEntries as $entry) {
            $this->client->request('GET', '/api/entry/'.$entry->getId());
            self::assertResponseIsSuccessful();
            $jsonData = self::getJsonResponse($this->client);

            self::assertIsArray($jsonData['crosspostedEntries']);

            $crossposts = array_filter($jsonData['crosspostedEntries'], function ($actual) use ($expectedEntries, $entry) {
                $match = array_filter($expectedEntries, function ($expected) use ($actual, $entry) {
                    return $actual['entryId'] !== $entry->getId()
                        && $actual['entryId'] === $expected->getId();
                });
                $matchCount = \count($match);
                if (0 === $matchCount) {
                    return false;
                } elseif (1 === $matchCount) {
                    return true;
                } else {
                    self::fail('crosspostedEntries contains duplicates');
                }
            });
            self::assertCount(\count($expectedEntries) - 1, $crossposts);
        }
    }
}
