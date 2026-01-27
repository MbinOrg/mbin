<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub;

use App\Tests\WebTestCase;

class CollectionExtractionTest extends WebTestCase
{
    private string $collectionUrl = 'https://some.server/some/collection';
    private array $collection;
    private string $incompleteCollectionUrl = 'https://some.server/some/collection2';
    private array $incompleteCollection;

    public function setUp(): void
    {
        parent::setUp();
        $this->collection = [
            'id' => $this->collectionUrl,
            'type' => 'Collection',
            'totalItems' => 3,
        ];
        $this->testingApHttpClient->collectionObjects[$this->collectionUrl] = $this->collection;
        $this->incompleteCollection = [
            'id' => $this->incompleteCollectionUrl,
            'type' => 'Collection',
        ];
        $this->testingApHttpClient->collectionObjects[$this->incompleteCollectionUrl] = $this->incompleteCollection;
    }

    public function testCollectionId(): void
    {
        self::assertEquals(3, $this->activityPubManager->extractTotalAmountFromCollection($this->collection));
    }

    public function testCollectionArray(): void
    {
        self::assertEquals(3, $this->activityPubManager->extractTotalAmountFromCollection($this->collectionUrl));
    }

    public function testIncompleteCollectionId(): void
    {
        self::assertNull($this->activityPubManager->extractTotalAmountFromCollection($this->incompleteCollectionUrl));
    }

    public function testIncompleteCollectionArray(): void
    {
        self::assertNull($this->activityPubManager->extractTotalAmountFromCollection($this->incompleteCollection));
    }
}
