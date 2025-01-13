<?php

// SPDX-FileCopyrightText: Copyright (c) 2016-2017 Emma <emma1312@protonmail.ch>
//
// SPDX-License-Identifier: Zlib

declare(strict_types=1);

namespace App\Markdown\Listener;

use App\Markdown\Event\BuildCacheContext;
use App\Markdown\Event\ConvertMarkdown;
use App\Repository\ApActivityRepository;
use App\Utils\UrlUtils;
use League\CommonMark\Output\RenderedContentInterface;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Fetch and store rendered HTML given the raw input and a generated context.
 */
final class CacheMarkdownListener implements EventSubscriberInterface
{
    private const ATTR_CACHE_ITEM = __CLASS__.' cache item';
    public const ATTR_NO_CACHE_STORE = 'no_cache_store';

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly ApActivityRepository $activityRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConvertMarkdown::class => [
                ['preConvertMarkdown', 64],
                ['postConvertMarkdown', -64],
            ],
        ];
    }

    public function preConvertMarkdown(ConvertMarkdown $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $cacheEvent = new BuildCacheContext($event, $request);
        $this->dispatcher->dispatch($cacheEvent);

        $item = $this->pool->getItem($cacheEvent->getCacheKey());

        if ($item->isHit()) {
            $content = $item->get();

            if ($content instanceof RenderedContentInterface) {
                $event->setRenderedContent($content);
                $event->stopPropagation();

                return;
            }
        }

        if (!$event->getAttribute(self::ATTR_NO_CACHE_STORE)) {
            $event->addAttribute(self::ATTR_CACHE_ITEM, $item);
        }
    }

    public function postConvertMarkdown(ConvertMarkdown $event): void
    {
        if ($event->getAttribute(self::ATTR_NO_CACHE_STORE)) {
            return;
        }

        $item = $event->getAttribute(self::ATTR_CACHE_ITEM);
        \assert($item instanceof CacheItemInterface);

        $item->set($event->getRenderedContent());

        try {
            if (method_exists($item, 'tag')) {
                $urls = array_map(fn ($item) => UrlUtils::getCacheKeyForMarkdownUrl($item), $this->getUrlsFromMarkdown($event->getMarkdown()));

                $this->logger->debug('added tags {t} to markdown', ['t' => $urls]);

                $item->tag($urls);
            }
        } catch (CacheException) {
        }

        $this->pool->save($item);

        $event->removeAttribute(self::ATTR_CACHE_ITEM);
    }

    /** @return string[] */
    private function getUrlsFromMarkdown(string $markdown): array
    {
        $words = preg_split('/[ \n]/', $markdown);
        $urls = [];
        foreach ($words as $word) {
            if (filter_var($word, FILTER_VALIDATE_URL)) {
                $entity = $this->activityRepository->findByObjectId($word);
                if (null === $entity) {
                    $urls[] = $word;
                }
            }
        }

        return $urls;
    }
}
