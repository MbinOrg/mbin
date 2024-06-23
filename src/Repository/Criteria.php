<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;

abstract class Criteria
{
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

    public const CONTENT_THREADS = 'threads';
    public const CONTENT_MICROBLOG = 'microblog';

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
    public ?User $user = null;
    public ?int $perPage = null;
    public string $type = 'all';
    public string $sortOption = EntryRepository::SORT_DEFAULT;
    public string $time = EntryRepository::TIME_DEFAULT;
    public string $visibility = VisibilityInterface::VISIBILITY_VISIBLE;
    public string $federation = self::AP_ALL;
    public string $content = self::CONTENT_THREADS;
    public bool $subscribed = false;
    public bool $moderated = false;
    public bool $favourite = false;
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
        if ($type) {
            $this->type = $type;
        }

        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

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
        if (null === $this->languages) {
            $this->languages = [];
        }
        array_push($this->languages, $lang);

        return $this;
    }

    public function showSortOption(?string $sortOption): self
    {
        if ($sortOption) {
            $this->sortOption = $sortOption;
        }

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

    // resolveTime() converts our internal values into ones for human presenation
    // $reverse = true indicates converting back, from human values to internal ones

    // This whole approach is a mess; this translation layer is temporary until
    // we have time to take a pass through the whole codebase and convert so there's
    // no such thing as multiple alternate value strings and translation layers
    // between them. This is just a temporary measure to produce desired output
    // until the whole layer goes away.
    public function resolveTime(?string $value, bool $reverse = false): ?string
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

        if ($reverse) {
            if ('all' === $value || '∞' === $value || null === $value) {
                return '∞';
            }
            $reversedRoutes = array_flip($routes);

            return $reversedRoutes[$value] ?? '∞';
        } else {
            return $routes[$value] ?? null;
        }
    }

    public function resolveType(?string $value): ?string
    {
        return match ($value) {
            'article', 'articles' => Entry::ENTRY_TYPE_ARTICLE,
            'link', 'links' => Entry::ENTRY_TYPE_LINK,
            'video', 'videos' => Entry::ENTRY_TYPE_VIDEO,
            'photo', 'photos', 'image', 'images' => Entry::ENTRY_TYPE_IMAGE,
            default => 'all'
        };
    }

    public function translateType(): string
    {
        return match ($this->resolveType($this->type)) {
            Entry::ENTRY_TYPE_ARTICLE => 'threads',
            Entry::ENTRY_TYPE_LINK => 'links',
            Entry::ENTRY_TYPE_VIDEO => 'videos',
            Entry::ENTRY_TYPE_IMAGE => 'photos',
            default => 'all',
        };
    }

    public function resolveSubscriptionFilter(): ?string
    {
        if ($this->subscribed) {
            return 'subscribed';
        } elseif ($this->moderated) {
            return 'moderated';
        } elseif ($this->favourite) {
            return 'favourites';
        } else {
            return 'all';
        }
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function setTime(?string $time): self
    {
        if ($time) {
            $this->time = $time;
        } else {
            $this->time = EntryRepository::TIME_DEFAULT;
        }

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

    public function getOption(string $key): string
    {
        return match ($key) {
            'sort' => $this->resolveSort($this->sortOption),
            'time' => '∞' === $this->resolveTime($this->time, true) ? 'all' : $this->resolveTime($this->time, true),
            'type' => $this->translateType(),
            'visibility' => $this->visibility,
            'federation' => $this->federation,
            'content' => $this->content,
            'tag' => $this->tag,
            'domain' => $this->domain,
            'subscription' => $this->resolveSubscriptionFilter(),
            default => throw new \LogicException('Unknown option: '.$key),
        };
    }
}
