<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\DTO\internal\EmbedDto;
use App\Entity\Entry;
use App\Entity\Image;
use App\Message\Contracts\MessageInterface;
use App\Message\EntryEmbedMessage;
use App\Repository\EntryRepository;
use App\Repository\ImageRepository;
use App\Service\EmbedService;
use App\Service\ImageManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class AttachEntryEmbedHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly KernelInterface $kernel,
        private readonly EmbedService $embed,
        private readonly ImageManagerInterface $manager,
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(EntryEmbedMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof EntryEmbedMessage)) {
            throw new \LogicException();
        }
        $entry = $this->entryRepository->find($message->entryId);

        if (!$entry) {
            throw new UnrecoverableMessageHandlingException('Entry not found');
        }

        if (!$entry->url) {
            $this->logger->debug('[AttachEntryEmbedHandler::doWork] returning, as the entry {id} does not have a url', ['id' => $entry->getId()]);

            return;
        }

        try {
            $embed = $this->embed->fetch($entry->url);
        } catch (\Exception $e) {
            return;
        }

        $type = $embed->getType();
        $isImage = $embed->isImageUrl();
        $embedValidHtml = $embed->valid && !$isImage;

        $cover = $this->fetchCover($entry, $embed);

        if (!$embedValidHtml && !$cover && !$isImage) {
            $this->logger->debug('[AttachEntryEmbedHandler::doWork] returning, as the embed is neither html, nor an image url and we could not extract an image from it either. URL: {u}', ['u' => $entry->url]);

            return;
        }

        $entry->type = $type;
        $entry->hasEmbed = $embedValidHtml || $isImage;
        if ($cover) {
            $this->logger->debug('[AttachEntryEmbedHandler::doWork] setting entry ({id}) image to new one', ['id' => $entry->getId()]);
            $entry->image = $cover;
        }

        $this->entityManager->flush();
    }

    private function fetchCover(Entry $entry, EmbedDto $embed): ?Image
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

        $this->logger->debug('[AttachEntryEmbedHandler::fetchCover] returning null, as the entry ({id}) already has an image and does not have an embed', ['id' => $entry->getId()]);

        return null;
    }

    private function getCoverUrl(Entry $entry, EmbedDto $embed): ?string
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
