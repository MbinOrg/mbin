<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Magazine;
use App\Repository\MagazineRepository;
use App\Service\SettingsManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('related_magazines')]
final class RelatedMagazinesComponent
{
    public const TYPE_TAG = 'tag';
    public const TYPE_MAGAZINE = 'magazine';
    public const TYPE_RANDOM = 'random';

    public int $limit = 4;
    public ?string $type = self::TYPE_RANDOM;
    public string $title = 'random_magazines';
    /** @var Magazine[] */
    public array $magazines = [];

    public function __construct(
        private readonly MagazineRepository $repository,
        private readonly CacheInterface $cache,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function mount(?string $magazine, ?string $tag): void
    {
        if ($tag) {
            $this->title = 'related_magazines';
            $this->type = self::TYPE_TAG;
        }

        if ($magazine) {
            $this->title = 'related_magazines';
            $this->type = self::TYPE_MAGAZINE;
        }

        $magazine = str_replace('@', '', $magazine ?? '');

        $magazineIds = $this->cache->get(
            "related_magazines_{$magazine}_{$tag}_{$this->type}_{$this->settingsManager->getLocale()}",
            function (ItemInterface $item) use ($magazine, $tag) {
                $item->expiresAfter(60 * 5); // 5 minutes

                $magazines = match ($this->type) {
                    self::TYPE_TAG => $this->repository->findRelated($tag),
                    self::TYPE_MAGAZINE => $this->repository->findRelated($magazine),
                    default => $this->repository->findRandom(),
                };

                $magazines = array_filter($magazines, fn ($m) => $m->name !== $magazine);

                return array_map(fn (Magazine $magazine) => $magazine->getId(), $magazines);
            }
        );

        $this->magazines = $this->repository->findBy(['id' => $magazineIds]);
    }
}
