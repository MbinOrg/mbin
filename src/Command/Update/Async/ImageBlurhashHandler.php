<?php

declare(strict_types=1);

namespace App\Command\Update\Async;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ImageBlurhashHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageManager $manager,
    ) {
    }

    /**
     * @return true
     */
    public function __invoke(ImageBlurhashMessage $message): bool
    {
        /** @var ImageRepository $repo */
        $repo = $this->entityManager->getRepository(Image::class);

        $image = $repo->find($message->id);

        $image->blurhash = $repo->blurhash($this->manager->getPath($image));

        $this->entityManager->persist($image);
        $this->entityManager->flush();

        return true;
    }
}
