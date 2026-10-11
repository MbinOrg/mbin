<?php
declare(strict_types=1);

namespace App\Service;

use App\DTO\internal\EmbedDto;
use App\Event\ActivityPub\CurlRequestBeginningEvent;
use App\Event\ActivityPub\CurlRequestFinishedEvent;
use App\Factory\WwwHttpClientFactory;
use Embed\Embed as BaseEmbed;
use Embed\Extractor;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class EmbedService
{
    public function __construct(
        private CacheInterface $cache,
        private SettingsManager $settings,
        private LoggerInterface $logger,
        private EventDispatcherInterface $dispatcher,
        private WwwHttpClientFactory $httpClientFactory,
    ) {
    }

    public function fetch(string $url): EmbedDto
    {
        if ($this->settings->isLocalUrl($url)) {
            if (ImageManager::isImageUrl($url)) {
                return $this->createLocalImageEmbed($url);
            }

            return $this->createFallbackEmbed($url);
        }

        return $this->cache->get(
            'embed_'.hash('sha256', $url),
            function (ItemInterface $item) use ($url) {
                $item->expiresAfter(3600);
                return $this->createPageEmbed($url);
            }
        );
    }

    private function createLocalImageEmbed(string $url): EmbedDto
    {
        $embed = new EmbedDto();
        $embed->url = $url;
        $embed->html = \sprintf('<img src="%s">', $url);
        return $embed;
    }

    private function createFallbackEmbed(string $url): EmbedDto
    {
        $urlEnc = htmlspecialchars($url);
        $embed = new EmbedDto();
        $embed->url = $url;
        $embed->html = \sprintf('<a href="%s">%s</a>', $urlEnc, 'Embed for '.$urlEnc);
        return $embed;
    }

    private function createPageEmbed(string $url): EmbedDto
    {
        $this->dispatcher->dispatch(new CurlRequestBeginningEvent($url));

        $this->logger->debug('[EmbedService::createPageEmbed] Start fetching', [
            'url' => $url,
        ]);

        try {
            $embed = $this->fetchEmbed($url);
            $oembed = $embed->getOEmbed();
            $this->dispatcher->dispatch(new CurlRequestFinishedEvent($url, true));
        } catch (\Exception $e) {
            $this->dispatcher->dispatch(new CurlRequestFinishedEvent($url, false, exception: $e));
            $this->logger->info('[EmbedService::createPageEmbed] Fetch failed: '.$e->getMessage());

            return $this->createFallbackEmbed($url);
        }

        $dto = new EmbedDto();
        $dto->url = $url;
        $dto->title = $embed->title;
        $dto->description = $embed->description;
        $dto->image = \strval($embed->image);
        $dto->embedUrl = $this->extractEmbedUrl($embed);

        try {
            if ($embed->code) {
                $dto->html = $this->cleanIframe($embed->code->html);
                $dto->width = $embed->code->width;
                $dto->height = $embed->code->height;
            }
        } catch (\TypeError $e) {
            $this->logger->info('[EmbedService::createPageEmbed] HTML prepare failed: '.$e->getMessage());
        }
        if (null === $dto->html) {
            $dto->html = $this->cleanIframe($oembed->html('html'));
            $dto->width = $oembed->int('width');
            $dto->height = $oembed->int('height');
        }

        $this->logger->debug('[EmbedService::createPageEmbed] Fetch success, returning', [
            'url' => $dto->url,
            'title' => $dto->title,
            'description' => $dto->description,
            'image' => $dto->image,
            'html' => $dto->html,
            'width' => $dto->width,
            'height' => $dto->height,
        ]);

        return $dto;
    }

    private function fetchEmbed(string $url): Extractor
    {
        $fetcher = new BaseEmbed($this->httpClientFactory->getEmbedCrawler());
        $embed = $fetcher->get($url);

        if ($this->detectFaultyRedirectEmbed($embed)) {
            $this->logger->debug('[EmbedService::fetch] Suspecting faulty redirect, refetching', [
                'requestUrl' => $url,
                'responseUrl' => $embed->getUri(),
            ]);

            $embed = $fetcher->get((string) $embed->getUri());
        }

        return $embed;
    }

    private function detectFaultyRedirectEmbed(Extractor $embed): bool
    {
        $request = $embed->getRequest();
        $response = $embed->getResponse();

        $isRedirected = $embed->getUri() !== $request->getUri()
            && !\in_array($response->getStatusCode(), [301, 302])
            && $response->getHeaderLine('location');

        $isEmptyEmbed = !(
            $embed->title
            || $embed->description
            || $embed->image
            || $embed->code?->html
        );

        return $isRedirected && $isEmptyEmbed;
    }

    private function cleanIframe(?string $html): ?string
    {
        if (!$html || str_contains($html, 'wp-embedded-content')) {
            return null;
        }

        $dom = $this->parseXmlToDom($html);

        $videoElements = $dom->getElementsByTagName('video');
        $iframeElements = $dom->getElementsByTagName('iframe');

        foreach ($videoElements as $videoElement) {
            $sourceUrl = $videoElement->getAttribute('src');
            $sourceMimeType = $videoElement->getAttribute('type');

            if (
                ($sourceUrl && VideoManager::isVideoUrl($sourceUrl))
                || ($sourceMimeType && VideoManager::isSupportedVideoMimeType($sourceMimeType))
            ) {
                continue; // supported video source, no need to check this one further
            }

            $isSupported = false;

            foreach ($videoElement->getElementsByTagName('source') as $sourceElement) {
                $sourceUrl = $sourceElement->getAttribute('src');
                $sourceMimeType = $sourceElement->getAttribute('type');

                if (
                    ($sourceUrl && VideoManager::isVideoUrl($sourceUrl))
                    || ($sourceMimeType && VideoManager::isSupportedVideoMimeType($sourceMimeType))
                ) {
                    $isSupported = true;
                    break;
                }
            }

            if (!$isSupported) {
                return null;
            }
        }

        foreach ($iframeElements as $iframeElement) {
            $iframeSrc = $iframeElement->getAttribute('src');

            if (
                $iframeSrc
                && (
                    VideoManager::isVideoUrl($iframeSrc)
                    || VideoManager::isStreamManifestUrl($iframeSrc)
                )
            ) {
                return null;
            }
        }

        return $html;
    }

    /**
     * This tries to find known locations of embed iframe URLs.
     */
    private function extractEmbedUrl(Extractor $embed): ?string
    {
        $embedHtml = $embed->getOEmbed()->html('html') ?: $embed->code?->html;
        if (null === $embedHtml) {
            return null;
        }

        $dom = $this->parseXmlToDom($embedHtml);

        foreach ($dom->getElementsByTagName('*') as $node) {
            /** @var \DOMElement $node */
            if ($node->hasAttribute('data-embed-url')) {
                return $node->getAttribute('data-embed-url');
            }

            if ('iframe' === $node->tagName) {
                $url = $node->getAttribute('src');
                if (str_contains($url, '/embed')) {
                    return $url;
                }
            }
        }

        return null;
    }

    private function parseXmlToDom(string $src): \DOMDocument
    {
        $dom = new \DOMDocument();
        $usesInternalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$src, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($usesInternalErrors);
        return $dom;
    }
}
