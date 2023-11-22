<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\User;

abstract class Criteria
{
    public const ENTRY_TYPE_ARTICLE = 'article';
    public const ENTRY_TYPE_LINK = 'link';

    public const FRONT_FEATURED = 'featured';
    public const FRONT_SUBSCRIBED = 'subscribed';
    public const FRONT_ALL = 'all';
    public const FRONT_MODERATED = 'moderated';
    public const SORT_ACTIVE = 'active';
    public const SORT_HOT = 'hot';
    public const SORT_NEW = 'newest';
    public const SORT_DEFAULT = self::SORT_HOT;

    public const SORT_OLD = 'oldest';
    public const SORT_TOP = 'top';
    public const SORT_COMMENTED = 'commented';

    public const TIME_3_HOURS = '3hours';
    public const TIME_6_HOURS = '6hours';
    public const TIME_12_HOURS = '12hours';
    public const TIME_DAY = 'day';
    public const TIME_WEEK = 'week';
    public const TIME_MONTH = 'month';
    public const TIME_YEAR = 'year';
    public const TIME_ALL = '∞';

    public const AP_ALL = 'all';
    public const AP_LOCAL = 'local';
    public const AP_FEDERATED = 'federated';

    public const FRONT_PAGE_OPTIONS = [
        self::FRONT_FEATURED,
        self::FRONT_SUBSCRIBED,
        self::FRONT_ALL,
        self::FRONT_MODERATED,
    ];

    public const SORT_OPTIONS = [
        self::SORT_ACTIVE,
        self::SORT_HOT,
        self::SORT_NEW,
        self::SORT_OLD,
        self::SORT_TOP,
        self::SORT_COMMENTED,
    ];

    public const TIME_OPTIONS = [
        self::TIME_6_HOURS,
        self::TIME_12_HOURS,
        self::TIME_DAY,
        self::TIME_WEEK,
        self::TIME_MONTH,
        self::TIME_YEAR,
        self::TIME_ALL,
    ];

    public const TIME_ROUTES_EN = [
        '3h',
        '6h',
        '12h',
        '1d',
        '1w',
        '1m',
        '1y',
        '∞',
        'all',
    ];

    public const AP_OPTIONS = [
        self::AP_ALL,
        self::AP_FEDERATED,
        self::AP_LOCAL,
    ];

    public int $page = 1;
    public ?Magazine $magazine = null;
    public ?Entry $entry = null;
    public ?Post $post = null;
    public ?User $user = null;
    public ?int $perPage = null;
    public bool $moderated = false;
    public bool $favourite = false;
    public bool $onlyParents = false;
    public ?string $type = null;
    public string $sortOption = EntryRepository::SORT_DEFAULT;
    public string $time = EntryRepository::TIME_DEFAULT;
    public string $visibility = VisibilityInterface::VISIBILITY_VISIBLE;
    public string $federation = self::AP_ALL;
    public bool $subscribed = false;
    public ?string $tag = null;
    public ?string $domain = null;
    public ?array $languages = null;

    public const THEME_MBIN = 'mbin';
    public const THEME_KBIN = 'kbin';
    public const THEME_AUTO = 'default';
    public const THEME_LIGHT = 'light';
    public const THEME_DARK = 'dark';
    public const THEME_SOLARIZED_AUTO = 'solarized';
    public const THEME_SOLARIZED_LIGHT = 'solarized-light';
    public const THEME_SOLARIZED_DARK = 'solarized-dark';
    public const THEME_TOKYO_NIGHT = 'tokyo-night';

    public const THEME_OPTIONS = [
      // 'Mbin' => SELF::THEME_MBIN, // TODO uncomment when theme is ready
      '/kbin' => self::THEME_KBIN,
      'default_theme_auto' => self::THEME_AUTO,
      'light' => self::THEME_LIGHT,
      'dark' => self::THEME_DARK,
      'solarized_auto' => self::THEME_SOLARIZED_AUTO,
      'solarized_light' => self::THEME_SOLARIZED_LIGHT,
      'solarized_dark' => self::THEME_SOLARIZED_DARK,
      'tokyo_night' => self::THEME_TOKYO_NIGHT,
    ];

    public function __construct(int $page)
    {
        $this->page = $page;
    }

    public function setFederation($feed): self
    {
        $this->federation = $feed;

        return $this;
    }

    public function setType(?string $type): self
    {
        $this->type = $type ?: $this->type;

        return $this;
    }

    public function setTag(string $name): self
    {
        $this->tag = $name;

        return $this;
    }

    public function setDomain(string $name): self
    {
        $this->domain = $name;

        return $this;
    }

    public function addLanguage(string $lang): self
    {
        $this->languages[] = $lang;

        return $this;
    }

    public function showSortOption(?string $sortOption): self
    {
        $this->sortOption = $sortOption ?: $this->sortOption;

        return $this;
    }

    protected function routes(): array
    {
        // @todo getRoute EntryManager
        return [
            'top' => Criteria::SORT_TOP,
            'hot' => Criteria::SORT_HOT,
            'active' => Criteria::SORT_ACTIVE,
            'newest' => Criteria::SORT_NEW,
            'oldest' => Criteria::SORT_OLD,
            'commented' => Criteria::SORT_COMMENTED,
        ];
    }

    public function resolveSort(?string $value): string
    {
        $routes = $this->routes();

        return $routes[$value] ?? $routes['hot'];
    }

    public function resolveTime(?string $value): ?string
    {
        // @todo
        $routes = [
            '3h' => Criteria::TIME_3_HOURS,
            '6h' => Criteria::TIME_6_HOURS,
            '12h' => Criteria::TIME_12_HOURS,
            '1d' => Criteria::TIME_DAY,
            '1w' => Criteria::TIME_WEEK,
            '1m' => Criteria::TIME_MONTH,
            '1y' => Criteria::TIME_YEAR,
            '∞' => Criteria::TIME_ALL,
            'all' => Criteria::TIME_ALL,
        ];

        return $routes[$value] ?? null;
    }

    public function resolveType(?string $value): ?string
    {
        // @todo
        $routes = [
            'article' => Entry::ENTRY_TYPE_ARTICLE,
            'articles' => Entry::ENTRY_TYPE_ARTICLE,
            'link' => Entry::ENTRY_TYPE_LINK,
            'links' => Entry::ENTRY_TYPE_LINK,
            'video' => Entry::ENTRY_TYPE_VIDEO,
            'videos' => Entry::ENTRY_TYPE_VIDEO,
            'photo' => Entry::ENTRY_TYPE_IMAGE,
            'photos' => Entry::ENTRY_TYPE_IMAGE,
            'image' => Entry::ENTRY_TYPE_IMAGE,
            'images' => Entry::ENTRY_TYPE_IMAGE,
        ];

        return $routes[$value] ?? null;
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function setTime(?string $time): self
    {
        $this->time = $time ?? EntryRepository::TIME_DEFAULT;

        return $this;
    }

    public function getSince(): \DateTimeImmutable
    {
        $since = new \DateTimeImmutable('@'.time());

        return match ($this->time) {
            Criteria::TIME_YEAR => $since->modify('-1 year'),
            Criteria::TIME_MONTH => $since->modify('-1 month'),
            Criteria::TIME_WEEK => $since->modify('-1 week'),
            Criteria::TIME_DAY => $since->modify('-1 day'),
            Criteria::TIME_12_HOURS => $since->modify('-12 hours'),
            Criteria::TIME_6_HOURS => $since->modify('-6 hours'),
            Criteria::TIME_3_HOURS => $since->modify('-3 hours'),
            default => throw new \LogicException(),
        };
    }
}
