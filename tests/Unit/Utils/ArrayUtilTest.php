<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Utils\ArrayUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArrayUtilTest extends TestCase
{
    #[DataProvider('provideSliceArrayIntoEqualPieces')]
    public function testSliceArrayIntoEqualPieces(array $array, int $size, array $expected): void
    {
        $result = ArrayUtils::sliceArrayIntoEqualPieces($array, $size);
        self::assertEquals($expected, $result);
    }

    public static function provideSliceArrayIntoEqualPieces(): array
    {
        return [
            [[1, 2, 3, 4, 5, 6, 7, 8, 9], 3, [[1, 2, 3], [4, 5, 6], [7, 8, 9]]],
            [[1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 3, [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10]]],
            [[1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 2, [[1, 2], [3, 4], [5, 6], [7, 8], [9, 10]]],
        ];
    }
}
