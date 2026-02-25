<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Utils\GeneralUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GeneralUtilTest extends TestCase
{
    #[DataProvider('providePaths')]
    public function testPathIgnoring(array $ignoredPaths, string $path, bool $shouldBeIgnored): void
    {
        self::assertEquals($shouldBeIgnored, GeneralUtil::shouldPathBeIgnored($ignoredPaths, $path));
    }

    public static function providePaths(): array
    {
        // our paths never start with a '/'
        return [
            [['/cache'], 'ca/fe/asdoihsd.png', false],
            [['/cache'], 'cache/ca/fe/asdoihsd.png', true],
            [['cache'], 'cache/ca/fe/asdoihsd.png', true],
            [['/cache'], 'cache/ca/fe/asdoihsd.png', true],
            [['/fe'], 'ca/fe/asdoihsd.png', false],
            [['fe'], 'ca/fe/asdoihsd.png', false],
            [['/fe', 'ca'], 'ca/fe/asdoihsd.png', true],
        ];
    }
}
