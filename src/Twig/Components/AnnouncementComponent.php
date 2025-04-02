<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\SiteRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Twig\Environment;

#[AsTwigComponent('announcement', template: 'components/_cached.html.twig')]
final class AnnouncementComponent
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SiteRepository $repository,
    ) {
    }

    public function getHtml(ComponentAttributes $attributes): string
    {
        return $this->twig->render(
            'components/announcement.html.twig',
            [
                'content' => $this->repository->findAll()[0]->announcement ?? '',
            ]
        );
    }
}
