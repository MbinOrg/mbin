<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine;

use App\Tests\WebTestCase;

class MagazinePurgeContentModLogApiTest extends WebTestCase
{
    public function testPurgeEntryModLog(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $this->entryManager->purge($admin, $entry);

        $this->client->request('GET', "/api/magazine/{$entry->magazine->getId()}/log");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        $this->validateModlog($jsonData, $entry->magazine, $admin);
        self::assertEquals($entry->title, $jsonData['items'][0]['subject']);
    }

    public function testPurgeEntryCommentModLog(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $entryComment = $this->createEntryComment('entryComment', entry: $entry);
        $admin = $this->getUserByUsername('admin', isAdmin: true);

        // otherwise we get persisting problems
        $this->entityManager->remove($this->activityRepository->findFirstActivitiesByTypeAndObject('Create', $entryComment));
        $this->entryCommentManager->purge($admin, $entryComment);

        $this->client->request('GET', "/api/magazine/{$entry->magazine->getId()}/log");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        $this->validateModlog($jsonData, $entry->magazine, $admin);
        self::assertEquals($entryComment->getShortTitle(), $jsonData['items'][0]['subject']);
    }

    public function testPurgePostModLog(): void
    {
        $post = $this->createPost('post');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $this->postManager->purge($admin, $post);

        $this->client->request('GET', "/api/magazine/{$post->magazine->getId()}/log");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        $this->validateModlog($jsonData, $post->magazine, $admin);
        self::assertEquals($post->getShortTitle(), $jsonData['items'][0]['subject']);
    }

    public function testPurgePostCommentModLog(): void
    {
        $post = $this->createPost('post');
        $postComment = $this->createPostComment('postComment', post: $post);
        $admin = $this->getUserByUsername('admin', isAdmin: true);

        // otherwise we get persisting problems
        $this->entityManager->remove($this->activityRepository->findFirstActivitiesByTypeAndObject('Create', $postComment));

        $this->postCommentManager->purge($admin, $postComment);

        $this->client->request('GET', "/api/magazine/{$post->magazine->getId()}/log");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        $this->validateModlog($jsonData, $post->magazine, $admin);
        self::assertEquals($postComment->getShortTitle(), $jsonData['items'][0]['subject']);
    }
}
