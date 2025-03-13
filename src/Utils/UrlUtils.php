<?php

declare(strict_types=1);

namespace App\Utils;

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
        $key = preg_replace('/[(){}\/:@]/', '_', $url);

        return "markdown_url_$key";
    }

    public static function getCacheKeyForMarkdownUserMention(string $url): string
    {
        $key = preg_replace('/[(){}\/:@]/', '_', $url);

        return "markdown_user_mention_$key";
    }

    public static function getCacheKeyForMarkdownMagazineMention(string $url): string
    {
        $key = preg_replace('/[(){}\/:@]/', '_', $url);

        return "markdown_magazine_mention_$key";
    }
}
