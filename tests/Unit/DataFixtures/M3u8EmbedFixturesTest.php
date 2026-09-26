<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\Controller\Dev\M3u8EmbedFixtureController;
use App\DataFixtures\M3u8EmbedFixtures;
use PHPUnit\Framework\TestCase;

class M3u8EmbedFixturesTest extends TestCase
{
    public function testOEmbedIframePointsToMasterPlaylist(): void
    {
        self::assertSame(
            '<iframe src="'.M3u8EmbedFixtureController::MASTER_URL.'" width="640" height="360" allowfullscreen></iframe>',
            M3u8EmbedFixtureController::IFRAME_HTML,
        );
    }

    public function testFixtureEntryAndPlaylistUseSameEndpoint(): void
    {
        self::assertSame(
            M3u8EmbedFixtureController::MASTER_URL,
            M3u8EmbedFixtures::ENTRY_URL,
        );

        $response = (new M3u8EmbedFixtureController())->masterPlaylist();
        $masterPlaylist = (string) $response->getContent();

        self::assertSame('application/vnd.apple.mpegurl', $response->headers->get('Content-Type'));
        self::assertStringStartsWith("#EXTM3U\n", $masterPlaylist);
        self::assertStringContainsString('#EXT-X-STREAM-INF:', $masterPlaylist);
        self::assertStringEndsWith("variant.m3u8\n", $masterPlaylist);
    }
}
