<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Image;
use App\Message\DeleteImageV2Message;
use App\Repository\ImageRepository;
use App\Service\ImageManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeleteImageV2Handler
{
    private int $chunkSize;

    public function __construct(
        KernelInterface $kernel,
        private EntityManagerInterface $entityManager,
        private ImageRepository $imageRepository,
        private ImageManagerInterface $imageManager,
        private LoggerInterface $logger,
    ) {
        $this->chunkSize = ('test' !== $kernel->getEnvironment()) ? 256 : 2;
    }

    public function __invoke(DeleteImageV2Message $message): void
    {
        try {
            /*
             * Split workload into chunks to balance overhead with probability of race-conditions. A race-condition can look like the following:
             *   1. one image in a chunk is detected as an orphan
             *   2. entity is deleted and transaction commited
             *   3. new content is created with the same image (resulting in the same file path) while $filesToDelete is iterated
             *   4. the file is deleted; the new image entity created by the new content now has an invalid path
             */
            $batches = array_chunk($message->images, $this->chunkSize, true);
            foreach ($batches as $batch) {
                $this->processBatch($batch);
            }
        } finally {
            gc_collect_cycles();
        }
    }

    private function processBatch(array $batch): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->getNativeConnection(); // calls connect() internally

        /** @var string[] $filesToDelete */
        $filesToDelete = $conn->transactional(function () use ($batch) {
            $filesToDelete = [];
            $hashes = array_map(fn ($str) => hex2bin($str), array_keys($batch));
            $images = $this->imageRepository->findMultipleBySha256AndLock($hashes);

            // images which not exist in DB can be deleted
            foreach ($batch as $hash => $filepath) {
                $hashBin = hex2bin($hash);
                if (!array_any($images, fn ($img) => $img->sha256 === $hashBin)) {
                    $filesToDelete[] = $filepath;
                }
            }

            // images which are not referenced can be deleted
            $referenced = $this->imageRepository->areImagesReferenced($images);
            foreach ($referenced as $imgId => $isReferenced) {
                if (!$isReferenced) {
                    $img = array_find($images, fn ($img) => $img->getId() === $imgId);
                    \assert($img instanceof Image);

                    $filesToDelete[] = $img->filePath;
                    $this->entityManager->remove($img);
                }
            }

            $this->entityManager->flush();
            return $filesToDelete;
        });

        foreach ($filesToDelete as $path) {
            try {
                $this->imageManager->remove($path);
            } catch (\Exception $e) {
                $this->logger->error('[DeleteImageV2Handler]: an error occurred when deleting an image file: {type} - {message}', [
                    'message' => $e->getMessage(),
                    'type' => \get_class($e),
                ]);
            }
        }
    }
}
