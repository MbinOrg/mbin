<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Embed as EmbedEntity;
use App\Entity\Entry;
use App\Entity\Image;
use App\Message\EntryEmbedMessage;
use App\Repository\EmbedRepository;
use App\Repository\EntryRepository;
use App\Repository\ImageRepository;
use App\Service\ImageManager;
use App\Utils\Embed;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class AttachEntryEmbedHandler
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly Embed $embed,
        private readonly EmbedRepository $embedRepository,
        private readonly ImageManager $manager,
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(EntryEmbedMessage $message): void
    {
        $entry = $this->entryRepository->find($message->entryId);

        if (!$entry) {
            throw new UnrecoverableMessageHandlingException('Entry not found');
        }

        if (!$entry->url) {
            return;
        }

        try {
            $embed = $this->embed->fetch($entry->url);
            $entity = new EmbedEntity($entry->url, true);
            $entity->html = $embed->html;
            $entity->title = $embed->title;
            $entity->description = $embed->description;
            $entity->image = $embed->image;
            $this->embedRepository->add($entity);
        } catch (\Exception $e) {
            return;
        }

        $html = $embed->html;
        $type = $embed->getType();
        $isImage = $embed->isImageUrl();

        $cover = $this->fetchCover($entry, $embed);

        if (!$html && !$cover && !$isImage) {
            return;
        }

        $entry->type = $type;
        $entry->hasEmbed = $html || $isImage;
        if ($cover) {
            $entry->image = $cover;
        }

        $this->entityManager->flush();
    }

    private function fetchCover(Entry $entry, Embed $embed): ?Image
    {
        if (!$entry->image) {
            if ($imageUrl = $this->getCoverUrl($entry, $embed)) {
                if ($tempFile = $this->fetchImage($imageUrl)) {
                    $image = $this->imageRepository->findOrCreateFromPath($tempFile);
                    if ($image && !$image->sourceUrl) {
                        $image->sourceUrl = $imageUrl;
                    }

                    return $image;
                }
            }
        }

        return null;
    }

    private function getCoverUrl(Entry $entry, Embed $embed): ?string
    {
        if ($embed->image) {
            return $embed->image;
        } elseif ($embed->isImageUrl()) {
            return $entry->url;
        }

        return null;
    }

    private function fetchImage(string $url): ?string
    {
        try {
            return $this->manager->download($url);
        } catch (\Exception $e) {
            return null;
        }
    }
}
