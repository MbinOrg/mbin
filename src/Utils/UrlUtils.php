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
}
