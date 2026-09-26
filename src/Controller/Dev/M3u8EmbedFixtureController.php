<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use Symfony\Component\HttpFoundation\Response;

class M3u8EmbedFixtureController
{
    public const string BASE_URL = 'http://127.0.0.1:8000/_dev/m3u8-embed';
    public const string MASTER_URL = self::BASE_URL.'/master.m3u8';

    public const string IFRAME_HTML = '<iframe src="'.self::MASTER_URL.'" width="640" height="360" allowfullscreen></iframe>';

    public function masterPlaylist(): Response
    {
        return new Response(
            "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-STREAM-INF:BANDWIDTH=1280000,RESOLUTION=1280x720\nvariant.m3u8\n",
            headers: ['Content-Type' => 'application/vnd.apple.mpegurl'],
        );
    }

    public function variantPlaylist(): Response
    {
        return new Response(
            "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:10\n#EXT-X-MEDIA-SEQUENCE:0\n#EXTINF:10.0,\nmissing-test-segment.ts\n#EXT-X-ENDLIST\n",
            headers: ['Content-Type' => 'application/vnd.apple.mpegurl'],
        );
    }
}
