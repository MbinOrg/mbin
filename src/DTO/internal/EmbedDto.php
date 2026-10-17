<?php
declare(strict_types=1);

namespace App\DTO\internal;

use App\Entity\Entry;
use App\Service\ImageManager;
use App\Service\VideoManager;

class EmbedDto
{
    /**
     * @var bool if true the embed could not be loaded and will contain a placeholder element
     */
    public bool $synthetic = true;
    public ?string $url = null;
    public ?string $embedUrl = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $image = null;
    public ?string $html = null;
    public ?int $width = null;
    public ?int $height = null;

    public function getType(): string
    {
        if ($this->isImageUrl()) {
            return Entry::ENTRY_TYPE_IMAGE;
        }

        if ($this->isVideoUrl()) {
            return Entry::ENTRY_TYPE_VIDEO;
        }

        if ($this->isVideoEmbed()) {
            return Entry::ENTRY_TYPE_VIDEO;
        }

        return Entry::ENTRY_TYPE_LINK;
    }

    /**
     * if false the embed is either synthetic or was loaded but has no content
     * (to differentiate compare with $synthetic or hasContent()).
     */
    public function isValid(): bool
    {
        return !$this->synthetic && $this->hasContent();
    }

    /**
     * indicates whenever an embed image or HTML could be extracted.
     */
    public function hasContent(): bool
    {
        return $this->html || $this->image;
    }

    public function isImageUrl(): bool
    {
        if (!$this->url) {
            return false;
        }

        return ImageManager::isImageUrl($this->url);
    }

    public function isVideoUrl(): bool
    {
        if (!$this->url) {
            return false;
        }

        return VideoManager::isVideoUrl($this->url);
    }

    public function isVideoEmbed(): bool
    {
        if (!$this->html) {
            return false;
        }

        return str_contains($this->html, 'video')
            || str_contains($this->html, 'youtube')
            || str_contains($this->html, 'vimeo')
            || str_contains($this->html, 'streamable'); // @todo
    }
}
