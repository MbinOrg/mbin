<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\MagazineRepository;
use App\Service\SettingsManager;
use Symfony\Bundle\SecurityBundle\Security;
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
        private readonly Security $security,
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
        /** @var User|null $user */
        $user = $this->security->getUser();

        $magazineIds = $this->cache->get(
            "related_magazines_{$magazine}_{$tag}_{$this->type}_{$this->settingsManager->getLocale()}_{$user?->getId()}",
            function (ItemInterface $item) use ($magazine, $tag, $user) {
                $item->expiresAfter(60 * 5); // 5 minutes

                $magazines = match ($this->type) {
                    self::TYPE_TAG => $this->repository->findRelated($tag, user: $user),
                    self::TYPE_MAGAZINE => $this->repository->findRelated($magazine, user: $user),
                    default => $this->repository->findRandom(user: $user),
                };

                $magazines = array_filter($magazines, fn ($m) => $m->name !== $magazine);

                return array_map(fn (Magazine $magazine) => $magazine->getId(), $magazines);
            }
        );

        $this->magazines = $this->repository->findBy(['id' => $magazineIds]);
    }
}
