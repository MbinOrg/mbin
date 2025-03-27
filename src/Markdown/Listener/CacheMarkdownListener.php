<?php

// SPDX-FileCopyrightText: Copyright (c) 2016-2017 Emma <emma1312@protonmail.ch>
//
// SPDX-License-Identifier: Zlib

declare(strict_types=1);

namespace App\Markdown\Listener;

use App\Markdown\CommonMark\CommunityLinkParser;
use App\Markdown\Event\BuildCacheContext;
use App\Markdown\Event\ConvertMarkdown;
use App\Repository\ApActivityRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\MentionManager;
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
        private readonly MentionManager $mentionManager,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
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
                $md = $event->getMarkdown();
                $urls = array_map(fn ($item) => UrlUtils::getCacheKeyForMarkdownUrl($item), $this->getMissingUrlsFromMarkdown($md));
                $mentions = array_map(fn ($item) => UrlUtils::getCacheKeyForMarkdownUserMention($item), $this->getMissingMentionsFromMarkdown($md));
                $magazineMentions = array_map(fn ($item) => UrlUtils::getCacheKeyForMarkdownMagazineMention($item), $this->getMissingMagazineMentions($md));

                $tags = array_unique(array_merge($urls, $mentions, $magazineMentions));

                $this->logger->debug('added tags {t} to markdown "{m}"', ['t' => $tags, 'm' => $md]);

                $item->tag($tags);
            }
        } catch (CacheException) {
        }

        $this->pool->save($item);

        $event->removeAttribute(self::ATTR_CACHE_ITEM);
    }

    /** @return string[] */
    private function getMissingUrlsFromMarkdown(string $markdown): array
    {
        $words = preg_split('/[ \n\[\]()]/', $markdown);
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

    /** @return string[] */
    private function getMissingMentionsFromMarkdown(string $markdown): array
    {
        $remoteMentions = $this->mentionManager->extract($markdown, MentionManager::REMOTE) ?? [];
        $missingMentions = [];

        foreach ($remoteMentions as $mention) {
            if (null === $this->userRepository->findOneBy(['apId' => $mention])) {
                $missingMentions[] = $mention;
            }
        }

        return $missingMentions;
    }

    /** @return string[] */
    private function getMissingMagazineMentions(string $markdown): array
    {
        $words = preg_split('/[ \n\[\]()]/', str_replace(chr(194).chr(160), "&nbsp;", $markdown));
        $missingCommunityMentions = [];
        foreach ($words as $word) {
            $matches = null;
            // Remove newline (\n), tab (\t), carriage return (\r), etc.
            $word2 = preg_replace('/[[:cntrl:]]/', '', $word);
            if (preg_match('/'.CommunityLinkParser::COMMUNITY_REGEX.'/', $word2, $matches)) {
                $apId = "$matches[1]@$matches[2]";
                $this->logger->debug("searching for magazine '{m}', original word: '{w}', word without cntrl: '{w2}'", ['m' => $apId, 'w' => $word, 'w2' => $word2]);
                try {
                    $magazine = $this->magazineRepository->findOneBy(['apId' => $apId]);
                    if (!$magazine) {
                        $missingCommunityMentions[] = $apId;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('An error occurred while looking for magazine "{m}": {t} - {msg}', ['m' => $apId, 't'=> get_class($e), 'msg' => $e->getMessage()]);
                }
            }
        }

        return $missingCommunityMentions;
    }
}
