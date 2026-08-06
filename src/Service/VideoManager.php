<?php

declare(strict_types=1);

namespace App\Service;

class VideoManager
{
    public const VIDEO_MIMETYPES = ['video/mp4', 'video/webm'];
    public const UNSUPPORTED_STREAM_EXTENSIONS = ['m3u8', 'm3u', 'mpd', 'ism', 'isml'];

    public static function isVideoUrl(string $url): bool
    {
        if (str_starts_with($url, 'data:')) {
            return self::isSupportedVideoMimeType(substr($url, 5));
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $urlExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $types = array_map(fn ($type) => str_replace('video/', '', $type), self::VIDEO_MIMETYPES);

        return \in_array($urlExt, $types, false);
    }

    public static function isUnsupportedStreamUrl(string $url): bool
    {
        if (str_starts_with($url, 'data:')) {
            return false;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $urlExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return \in_array($urlExt, self::UNSUPPORTED_STREAM_EXTENSIONS, true);
    }

    public static function isSupportedVideoMimeType(?string $mimeType): bool
    {
        if (null === $mimeType || '' === trim($mimeType)) {
            return false;
        }

        $normalizedMimeType = strtolower(explode(';', trim($mimeType))[0]);

        return \in_array($normalizedMimeType, self::VIDEO_MIMETYPES, true);
    }
}
