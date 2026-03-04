<?php

declare(strict_types=1);

namespace App\EventSubscriber\Image;

use App\Event\ImagePostProcessEvent;
use App\Service\ImageManager;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class ImageCompressSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ImageManager $imageManager,
        private SettingsManager $settingsManager,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImagePostProcessEvent::class => 'compressImage',
        ];
    }

    public function compressImage(ImagePostProcessEvent $event): void
    {
        $extension = pathinfo($event->targetFilePath, PATHINFO_EXTENSION);
        if (!$this->imageManager->compressUntilSize($event->source, $extension, $this->settingsManager->getMaxImageBytes())) {
            if (filesize($event->source) > $this->settingsManager->getMaxImageBytes()) {
                $this->logger->warning('Was not able to compress image {i} to size {b}', ['i' => $event->source, 'b' => $this->settingsManager->getMaxImageBytes()]);
            }
        }
    }
}
