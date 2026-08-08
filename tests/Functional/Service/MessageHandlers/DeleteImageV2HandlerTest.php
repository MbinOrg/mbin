<?php
declare(strict_types=1);

namespace App\Tests\Functional\Service\MessageHandlers;

use App\Entity\Image;
use App\Message\DeleteImageV2Message;
use App\MessageHandler\DeleteImageV2Handler;
use App\Tests\WebTestCase;

class DeleteImageV2HandlerTest extends WebTestCase
{
    private DeleteImageV2Handler $handler;

    public function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->getService(DeleteImageV2Handler::class);
    }

    public function testCanDeleteAllImages(): void
    {
        $img1 = $this->createTestImage('test1.png');
        $img2 = $this->createTestImage('test2.png');
        $img3 = $this->createTestImage('test3.png');
        $message = DeleteImageV2Message::fromImages([$img1, $img2, $img3]);

        try {
            ($this->handler)($message);

            self::assertNull($this->imageRepository->findOneBySha256($img1->sha256));
            self::assertNull($this->imageRepository->findOneBySha256($img2->sha256));
            self::assertNull($this->imageRepository->findOneBySha256($img3->sha256));
            self::assertFalse(file_exists($this->imageFilePath($img1->filePath)));
            self::assertFalse(file_exists($this->imageFilePath($img2->filePath)));
            self::assertFalse(file_exists($this->imageFilePath($img3->filePath)));
        } finally {
            $this->removeImageFile($img1->filePath);
            $this->removeImageFile($img2->filePath);
            $this->removeImageFile($img3->filePath);
        }
    }

    public function testCanSkipReferencedImages(): void
    {
        $img1 = $this->createTestImage('test1.png');
        $img2 = $this->createTestImage('test2.png');
        $img3 = $this->createTestImage('test3.png');
        $message = DeleteImageV2Message::fromImages([$img1, $img2, $img3]);

        try {
            $entry = $this->getEntryByTitle('entry');
            $entry->image = $img2;
            $this->entityManager->persist($entry);
            $this->entityManager->flush();

            ($this->handler)($message);

            self::assertNull($this->imageRepository->findOneBySha256($img1->sha256));
            self::assertNotNull($this->imageRepository->findOneBySha256($img2->sha256));
            self::assertNull($this->imageRepository->findOneBySha256($img3->sha256));
            self::assertFalse(file_exists($this->imageFilePath($img1->filePath)));
            self::assertTrue(file_exists($this->imageFilePath($img2->filePath)));
            self::assertFalse(file_exists($this->imageFilePath($img3->filePath)));
            self::assertEquals($img2->getId(), $this->imageRepository->findOneBySha256($img2->sha256)->getId());
        } finally {
            $this->removeImageFile($img1->filePath);
            $this->removeImageFile($img2->filePath);
            $this->removeImageFile($img3->filePath);
        }
    }

    public function testCanDeleteOrphansImages(): void
    {
        $img1 = $this->createTestImage('test1.png');
        $img2 = $this->createTestImage('test2.png');
        $img3 = $this->createTestImage('test3.png');
        $message = DeleteImageV2Message::fromImages([$img1, $img2, $img3]);

        try {
            $entry = $this->getEntryByTitle('entry');
            $entry->image = $img2;
            $this->entityManager->persist($entry);
            $this->entityManager->flush();

            $this->entityManager->remove($img1);
            $this->entityManager->remove($img3);
            $this->entityManager->flush();

            ($this->handler)($message);

            self::assertNull($this->imageRepository->findOneBySha256($img1->sha256));
            self::assertNotNull($this->imageRepository->findOneBySha256($img2->sha256));
            self::assertNull($this->imageRepository->findOneBySha256($img3->sha256));
            self::assertFalse(file_exists($this->imageFilePath($img1->filePath)));
            self::assertTrue(file_exists($this->imageFilePath($img2->filePath)));
            self::assertFalse(file_exists($this->imageFilePath($img3->filePath)));
            self::assertEquals($img2->getId(), $this->imageRepository->findOneBySha256($img2->sha256)->getId());
        } finally {
            $this->removeImageFile($img1->filePath);
            $this->removeImageFile($img2->filePath);
            $this->removeImageFile($img3->filePath);
        }
    }

    private function createTestImage($fileName): Image
    {
        if (!file_exists($this->imageUploadTmpDir)) {
            if (!mkdir($this->imageUploadTmpDir)) {
                throw new \Exception('The copy dir could not be created');
            }
        }

        $filePathName = $fileName.'.'.bin2hex(random_bytes(32));
        $f = fopen($this->imageFilePath($filePathName), 'w');
        if (!$f) {
            throw new \Exception('The dummy file could not be created');
        }
        fclose($f);

        $image = new Image(
            $fileName,
            $filePathName,
            hash('sha256', $fileName),
            100,
            100,
            null,
        );
        $this->entityManager->persist($image);
        $this->entityManager->flush();

        return $image;
    }

    private function removeImageFile(string $filename): void
    {
        @unlink($this->imageFilePath($filename));
    }

    private function imageFilePath(string $filename): string
    {
        return $this->imageUploadTmpDir.'../../../public/media/'.$filename;
    }
}
