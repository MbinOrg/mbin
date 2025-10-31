<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Magazine;

class MagazineThemeDto
{
    public ?Magazine $magazine = null;
    public ?ImageDto $icon = null;
    public ?ImageDto $banner = null;
    public ?string $customCss = null;
    public ?string $customJs = null;
    public ?string $primaryColor = null;
    public ?string $primaryDarkerColor = null;
    public ?string $backgroundImage = null;

    public function __construct(Magazine $magazine)
    {
        $this->magazine = $magazine;
        $this->customCss = $magazine->customCss;
    }

    public function create(?ImageDto $icon)
    {
        $this->icon = $icon;
    }

    public function createBanner(ImageDto $banner): void
    {
        $this->banner = $banner;
    }
}
