<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Entry;
use App\Repository\EntryRepository;
use App\Service\MentionManager;
use App\Service\SettingsManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('related_entries')]
final class RelatedEntriesComponent
{
    public const TYPE_TAG = 'tag';
    public const TYPE_MAGAZINE = 'magazine';
    public const TYPE_RANDOM = 'random';

    public int $limit = 4;
    public ?string $type = self::TYPE_RANDOM;
    public ?Entry $entry = null;
    public string $title = 'random_entries';

    /** @var Entry[] */
    public array $entries = [];

    public function __construct(
        private readonly EntryRepository $repository,
        private readonly CacheInterface $cache,
        private readonly SettingsManager $settingsManager,
        private readonly MentionManager $mentionManager
    ) {
    }

    public function mount(?string $magazine, ?string $tag): void
    {
        if ($tag) {
            $this->title = 'related_entries';
            $this->type = self::TYPE_TAG;
        }

        if ($magazine) {
            $this->title = 'related_entries';
            $this->type = self::TYPE_MAGAZINE;
        }

        $entryId = $this->entry?->getId();
        $magazine = str_replace('@', '', $magazine ?? '');

        $entryIds = $this->cache->get(
            "related_entries_{$magazine}_{$tag}_{$entryId}_{$this->type}_{$this->settingsManager->getLocale()}",
            function (ItemInterface $item) use ($magazine, $tag) {
                $item->expiresAfter(60 * 5); // 5 minutes

                $entries = match ($this->type) {
                    self::TYPE_TAG => $this->repository->findRelatedByMagazine($tag, $this->limit + 20),
                    self::TYPE_MAGAZINE => $this->repository->findRelatedByTag(
                        $this->mentionManager->getUsername($magazine),
                        $this->limit + 20
                    ),
                    default => $this->repository->findLast($this->limit + 150),
                };

                $entries = array_filter($entries, fn (Entry $e) => !$e->isAdult && !$e->magazine->isAdult);

                if (\count($entries) > $this->limit) {
                    shuffle($entries); // randomize the order
                    $entries = \array_slice($entries, 0, $this->limit);
                }

                return array_map(fn (Entry $entry) => $entry->getId(), $entries);
            }
        );

        $this->entries = $this->repository->findBy(['id' => $entryIds]);
    }
}
