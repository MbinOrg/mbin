<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Image;
use Twig\Extension\RuntimeExtensionInterface;

class MediaExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly string $storageUrl
    ) {
    }

    public function getPublicPath(Image $image): ?string
    {
        if ($image->filePath) {
            return $this->storageUrl.'/'.$image->filePath;
        }

        return $image->sourceUrl;
    }
}
