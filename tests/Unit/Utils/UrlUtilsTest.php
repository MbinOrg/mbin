<?php
declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Tests\WebTestCase;
use App\Utils\UrlUtils;

class UrlUtilsTest extends WebTestCase
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
}
