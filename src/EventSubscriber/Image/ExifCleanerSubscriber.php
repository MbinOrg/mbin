<?php

declare(strict_types=1);

namespace App\EventSubscriber\Image;

use App\Event\ImagePostProcessEvent;
use App\Utils\ExifCleaner;
use App\Utils\ExifCleanMode;
use App\Utils\ImageOrigin;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExifCleanerSubscriber implements EventSubscriberInterface
{
    private ExifCleanMode $uploadedCleanMode;
    private ExifCleanMode $externalCleanMode;

    public function __construct(
        private readonly ExifCleaner $cleaner,
        private readonly ContainerBagInterface $params,
        private readonly LoggerInterface $logger,
    ) {
        $this->uploadedCleanMode = $params->get('exif_clean_mode_uploaded');
        $this->externalCleanMode = $params->get('exif_clean_mode_external');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImagePostProcessEvent::class => ['cleanExif'],
        ];
    }

    public function cleanExif(ImagePostProcessEvent $event)
    {
        $mode = $this->getCleanMode($event->origin);
        $this->logger->debug(
            'ImagePostProcessEvent:ExifCleanerSubscriber: cleaning image:',
            [
                'source' => $event->source,
                'origin' => $event->origin,
                'sha256' => hash_file('sha256', $event->source, false),
                'mode' => $mode,
            ]
        );
        $this->cleaner->cleanImage($event->source, $mode);
    }

    private function getCleanMode(ImageOrigin $origin): ExifCleanMode
    {
        return match ($origin) {
            ImageOrigin::Uploaded => $this->uploadedCleanMode,
            ImageOrigin::External => $this->externalCleanMode,
        };
    }
}
