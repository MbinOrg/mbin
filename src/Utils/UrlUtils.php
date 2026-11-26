<?php

declare(strict_types=1);

namespace App\Utils;

use League\Uri\UriString;
use Symfony\Component\HttpFoundation\Request;

class UrlUtils
{
    public static function isActivityPubRequest(?Request $request): bool
    {
        if (null === $request) {
            return true;
        }
        $acceptValue = $request->headers->get('Accept', default: 'html');

        return str_contains($acceptValue, 'application/activity+json')
            || str_contains($acceptValue, 'application/ld+json');
    }

    public static function getCacheKeyForMarkdownUrl(string $url): string
    {
        $key = preg_replace(RegPatterns::INVALID_TAG_CHARACTERS, '_', $url);

        return "markdown_url_$key";
    }

    public static function getCacheKeyForMarkdownUserMention(string $url): string
    {
        $key = preg_replace(RegPatterns::INVALID_TAG_CHARACTERS, '_', $url);

        return "markdown_user_mention_$key";
    }

    public static function getCacheKeyForMarkdownMagazineMention(string $url): string
    {
        $key = preg_replace(RegPatterns::INVALID_TAG_CHARACTERS, '_', $url);

        return "markdown_magazine_mention_$key";
    }

    public static function extractUrlsFromString(string $text): array
    {
        $words = preg_split(RegPatterns::URL_SEPARATOR_REGEX, $text);
        $urls = [];
        foreach ($words as $word) {
            if (filter_var($word, FILTER_VALIDATE_URL)) {
                $urls[] = $word;
            }
        }

        return $urls;
    }

    /**
     * Checks that a given sub-path will not change the absolute URL path of the url to a different parent when appended to the base URL.
     * An example for an ascending sub-path would be: baseUrl = https://example.com/parent/ subPath = ../otherParent/child.
     *
     * @param string $baseUrl The URL where the sub-path will be appended to. Must end with a '/'.
     * @param string $subPath the relative URL to be appended to $baseUrl
     */
    public static function checkUrlSubpathNotAscending(string $baseUrl, string $subPath): bool
    {
        $baseUri = UriString::normalize($baseUrl);
        $absoluteUri = UriString::normalize(UriString::resolve($subPath, $baseUri));

        return str_starts_with($absoluteUri, $baseUri);
    }

    public static function getFileExtension(string $url): ?string
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'], $parsed['path'])) {
            return null;
        }
        if ('http' !== $parsed['scheme'] && 'https' !== $parsed['scheme']) {
            return null;
        }

        if ('cdn.bsky.app' === $parsed['host']) {
            // QUIRK Bluesky has a '?' before the extension
            $path = $parsed['path'].'?'.$parsed['query'];
            $markerIdx = strpos($path, '?.');
            if ($markerIdx) {
                return mb_strtolower(substr($path, $markerIdx + 2));
            }
        }

        return mb_strtolower(pathinfo($parsed['path'], PATHINFO_EXTENSION));
    }
}
