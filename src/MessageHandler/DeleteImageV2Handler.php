<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\Contracts\MessageInterface;
use App\Message\DeleteImageV2Message;
use App\Repository\ImageRepository;
use App\Service\ImageManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeleteImageV2Handler extends MbinMessageHandler
{
    public function __construct(
        KernelInterface $kernel,
        private EntityManagerInterface $entityManager,
        private ImageRepository $imageRepository,
        private ImageManagerInterface $imageManager,
    ) {
        parent::__construct($this->entityManager, $kernel);
    }

    public function __invoke(DeleteImageV2Message $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!$message instanceof DeleteImageV2Message) {
            throw new \LogicException();
        }

        // querying each image individually is inefficient but lowers the probability of a race-condition
        foreach ($message->images as $hash => $filepath) {
            $img = $this->imageRepository->findOneBySha256(hex2bin($hash));
            if (null !== $img) {
                if (!$this->imageRepository->isImageReferenced($img)) {
                    // this should lock the row and prevent concurrent re-referencing
                    $this->entityManager->remove($img);
                    $this->entityManager->flush();

                    $deleteFile = true;
                } else {
                    $deleteFile = false;
                }
            } else {
                $deleteFile = true;
            }

            if ($deleteFile && null !== $filepath) {
                $this->imageManager->remove($filepath);
            }
        }
    }
}
