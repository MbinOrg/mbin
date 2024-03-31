<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Entry;
use App\Entity\Image;
use App\Message\EntryEmbedMessage;
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
        } catch (\Exception $e) {
            return;
        }

        $hasHtml = (bool) $embed->html;
        $isImage = $embed->isImageUrl();

        if (!$hasHtml && !$isImage) {
            return;
        }

        $entry->type = $embed->getType();
        $entry->hasEmbed = $hasHtml || $isImage;

        if (!$entry->image) {
            if ($coverUrl = $this->getCoverUrl($entry, $embed)) {
                if ($cover = $this->fetchCover($coverUrl)) {
                    $entry->image = $cover;
                }
            }
        }

        $this->entityManager->flush();
    }

    private function getCoverUrl(Entry $entry, Embed $embed)
    {
        return $embed->image ?: ($embed->isImageUrl() ? $entry->url : null);
    }

    private function fetchCover(string $imageUrl): ?Image
    {
        if ($tempFile = $this->fetchImage($imageUrl)) {
            $cover = $this->imageRepository->findOrCreateFromPath($tempFile);
            if ($cover && !$cover->filePath) {
                $cover->sourceUrl = $imageUrl;
            }

            return $cover;
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
