<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service\ActivityPub;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\ActivityPubManager;
use App\Service\ImageManager;
use PHPUnit\Framework\TestCase;

class ActivityPubManagerTest extends TestCase
{
    public function testHandleImagesFindsBestImageOfOne()
    {
        $attachment = [
            [
                'type' => 'Image',
                'url' => 'https://example.com/image1.jpg',
            ],
        ];

        $imageManager = $this->createStub(ImageManager::class);
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageManager->method('download')->willReturnArgument(0);
        $imageRepository->method('findOrCreateFromPath')->with('https://example.com/image1.jpg')->willReturn(
            new Image('success.jpg', '', '0000000000000000000000000000000000000000000000000000000000000000', 1, 1, '')
        );

        $mng = $this->initiateMocked(ActivityPubManager::class, ['imageManager' => $imageManager, 'imageRepository' => $imageRepository]);
        $img = $mng->handleImages($attachment);

        self::assertNotNull($img);
        self::assertSame('success.jpg', $img->fileName);
    }

    public function testHandleImagesFindsBestImageOfMultiAscResolution()
    {
        $attachment = [
            [
                'type' => 'Image',
                'name' => 'A',
                'url' => 'https://example.com/image-a-1.jpg',
                'height' => 100,
                'width' => 101,
            ],
            [
                'type' => 'Image',
                'name' => 'B',
                'url' => 'https://example.com/image-b-1.jpg',
                'height' => 200,
                'width' => 201,
            ],
            [
                'type' => 'Image',
                'name' => 'A',
                'url' => 'https://example.com/image-a-2.jpg',
                'height' => 110,
                'width' => 111,
            ],
        ];

        $imageManager = $this->createStub(ImageManager::class);
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageManager->method('download')->willReturnArgument(0);
        $imageRepository->method('findOrCreateFromPath')->with('https://example.com/image-a-2.jpg')->willReturn(
            new Image('success.jpg', '', '0000000000000000000000000000000000000000000000000000000000000000', 1, 1, '')
        );

        $mng = $this->initiateMocked(ActivityPubManager::class, ['imageManager' => $imageManager, 'imageRepository' => $imageRepository]);
        $img = $mng->handleImages($attachment);

        self::assertNotNull($img);
        self::assertSame('success.jpg', $img->fileName);
    }

    public function testHandleImagesFindsBestImageOfMultiDescResolution()
    {
        $attachment = [
            [
                'type' => 'Image',
                'name' => 'A',
                'url' => 'https://example.com/image-a-1.jpg',
                'height' => 110,
                'width' => 111,
            ],
            [
                'type' => 'Image',
                'name' => 'B',
                'url' => 'https://example.com/image-b-1.jpg',
                'height' => 200,
                'width' => 201,
            ],
            [
                'type' => 'Image',
                'name' => 'A',
                'url' => 'https://example.com/image-a-2.jpg',
                'height' => 100,
                'width' => 101,
            ],
        ];

        $imageManager = $this->createStub(ImageManager::class);
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageManager->method('download')->willReturnArgument(0);
        $imageRepository->method('findOrCreateFromPath')->with('https://example.com/image-a-1.jpg')->willReturn(
            new Image('success.jpg', '', '0000000000000000000000000000000000000000000000000000000000000000', 1, 1, '')
        );

        $mng = $this->initiateMocked(ActivityPubManager::class, ['imageManager' => $imageManager, 'imageRepository' => $imageRepository]);
        $img = $mng->handleImages($attachment);

        self::assertNotNull($img);
        self::assertSame('success.jpg', $img->fileName);
    }

    public function testHandleImagesIgnoresNonImage()
    {
        $attachment = [
            [
                'type' => 'Other',
                'url' => 'https://example.com/document.pdf',
            ],
        ];

        $imageManager = $this->createStub(ImageManager::class);
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageManager->method('download')->willReturnArgument(0);
        $imageRepository->method('findOrCreateFromPath')->willReturn(
            new Image('success.jpg', '', '0000000000000000000000000000000000000000000000000000000000000000', 1, 1, '')
        );

        $mng = $this->initiateMocked(ActivityPubManager::class, ['imageManager' => $imageManager, 'imageRepository' => $imageRepository]);
        $img = $mng->handleImages($attachment);

        self::assertNull($img);
    }

    public function testHandleImagesHandlesValidString()
    {
        $attachment = 'https://example.com/image.png';

        $imageManager = $this->createStub(ImageManager::class);
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageManager->method('download')->willReturnArgument(0);
        $imageRepository->method('findOrCreateFromPath')->with('https://example.com/image.png')->willReturn(
            new Image('success.jpg', '', '0000000000000000000000000000000000000000000000000000000000000000', 1, 1, '')
        );

        $mng = $this->initiateMocked(ActivityPubManager::class, ['imageManager' => $imageManager, 'imageRepository' => $imageRepository]);
        $img = $mng->handleImages($attachment);

        self::assertNotNull($img);
        self::assertSame('success.jpg', $img->fileName);
    }

    public function testHandleImagesHandlesInvalidString()
    {
        $attachment = 'image.png';

        $imageManager = $this->createStub(ImageManager::class);
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageManager->method('download')->willReturnArgument(0);
        $imageRepository->method('findOrCreateFromPath')->with('image.png')->willReturn(
            new Image('dummy.jpg', '', '0000000000000000000000000000000000000000000000000000000000000000', 1, 1, '')
        );

        $mng = $this->initiateMocked(ActivityPubManager::class, ['imageManager' => $imageManager, 'imageRepository' => $imageRepository]);
        $img = $mng->handleImages($attachment);

        self::assertNull($img);
    }

    /**
     * @template T
     *
     * @param class-string<T>       $class
     * @param array<string, object> $mocks
     *
     * @return T
     */
    private function initiateMocked(string $class, array $mocks = []): object
    {
        $reflect = new \ReflectionClass($class);
        $constructor = $reflect->getConstructor();

        $consParams = [];
        foreach ($constructor->getParameters() as $param) {
            $mock = $mocks[$param->getName()] ?? $this->createMock($param->getType()->getName());
            $consParams[] = $mock;
        }

        return $reflect->newInstanceArgs($consParams);
    }
}
