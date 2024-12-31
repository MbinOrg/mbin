<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Utils\Slugger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SluggerTest extends TestCase
{
    #[DataProvider('provider')]
    public function testCamelCase(string $input, string $output): void
    {
        $this->assertEquals($output, Slugger::camelCase($input));
    }

    public static function provider(): array
    {
        return [
            ['Lorem ipsum', 'loremIpsum'],
            ['LOremIpsum', 'lOremIpsum'],
            ['LORemIpsum', 'lORemIpsum'],
        ];
    }
}
