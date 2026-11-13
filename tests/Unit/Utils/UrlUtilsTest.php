<?php
declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Utils\UrlUtils;
use PHPUnit\Framework\TestCase;

class UrlUtilsTest extends TestCase
{
    public function testCheckUrlSubpathNotAscending(): void
    {
        self::assertTrue(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', 'abc'));
        self::assertTrue(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', 'abc/../def'));
        self::assertTrue(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', './abc//def'));
        self::assertTrue(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', 'https://example.com/prefix/abc'));

        self::assertFalse(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix', './abc'));
        self::assertFalse(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', '/abc/'));
        self::assertFalse(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', '../prefix2'));
        self::assertFalse(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', '../prefix2/abc'));
        self::assertFalse(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', 'https://example.com/prefix2/abc'));
        self::assertFalse(UrlUtils::checkUrlSubpathNotAscending('https://example.com/prefix/', 'https://example.net/prefix/abc'));
    }

    public function testCheckValidUrl()
    {
        self::assertTrue(UrlUtils::checkValidUrl('http://example.com'));
        self::assertTrue(UrlUtils::checkValidUrl('https://example.com/path'));
        self::assertTrue(UrlUtils::checkValidUrl('example.com'));
        self::assertTrue(UrlUtils::checkValidUrl('https://user:pass@example.com'));

        self::assertFalse(UrlUtils::checkValidUrl('ftp://example.com/file.tar'));
        self::assertTrue(UrlUtils::checkValidUrl('ftp://example.com/file.tar', true));

        self::assertFalse(UrlUtils::checkValidUrl('user:pass@example.com'));
        self::assertFalse(UrlUtils::checkValidUrl('ssh://example.com', true));
        self::assertFalse(UrlUtils::checkValidUrl('javascript:alert();'));
        self::assertFalse(UrlUtils::checkValidUrl("https://\texample.com/"));
        self::assertFalse(UrlUtils::checkValidUrl("https://\0example.com/"));
        self::assertFalse(UrlUtils::checkValidUrl(""));
        self::assertFalse(UrlUtils::checkValidUrl("  "));
        self::assertFalse(UrlUtils::checkValidUrl(" \t "));
    }
}
