<?php

declare(strict_types=1);

namespace App\Service;

class VideoManager
{
    public const array VIDEO_MIMETYPES = ['video/mp4', 'video/webm', 'video/quicktime', 'video/ogg'];
    public const array VIDEO_FILE_EXTENSIONS = ['mp4', 'webm', 'mov', 'ogv', 'ogg'];

    public static function isVideoUrl(string $url): bool
    {
        if (str_starts_with($url, 'data:')) {
            return self::isSupportedVideoMimeType(substr($url, 5));
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $urlExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return \in_array($urlExt, self::VIDEO_FILE_EXTENSIONS, false);
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
