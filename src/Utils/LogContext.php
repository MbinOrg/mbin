<?php

declare(strict_types=1);

namespace App\Utils;

final class LogContext
{
    public static function host(mixed $url): ?string
    {
        $host = \is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        return \is_string($host) ? mb_strcut($host, 0, 160, 'UTF-8') : null;
    }

    public static function identifier(mixed $url): ?string
    {
        if (!\is_string($url)) {
            return null;
        }

        $parts = parse_url($url);
        if (!isset($parts['host'], $parts['scheme']) || !\in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return mb_strcut($parts['scheme'].'://'.$parts['host'].$port.($parts['path'] ?? ''), 0, 160, 'UTF-8');
    }

    public static function type(mixed $type): ?string
    {
        return \is_string($type) && 1 === preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $type) ? $type : null;
    }
}
