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

    public function testFilter(): void
    {
        $arr = [1, 2, 3, 4, 5];
        $iter = new \ArrayIterator($arr);

        self::assertSame([2, 4], array_values(ArrayUtils::filter($arr, fn ($item) => ($item & 1) === 0)));
        self::assertSame([2, 4], array_values(ArrayUtils::filter($iter, fn ($item) => ($item & 1) === 0)));
    }

    public function testMap(): void {
        $arr = [1, 2, 3, 4, 5];
        $iter = new \ArrayIterator($arr);

        self::assertSame([0, 1, 2, 3, 4], ArrayUtils::map($arr, fn ($item) => $item - 1));
        self::assertSame([0, 1, 2, 3, 4], ArrayUtils::map($iter, fn ($item) => $item - 1));
    }

    public function testForeach(): void
    {
        $arr = [(object) ['a' => 1], (object) ['a' => 2], (object) ['a' => 3, 'b' => 4]];
        $iter = new \ArrayIterator([(object) ['a' => 1], (object) ['a' => 2], (object) ['a' => 3, 'b' => 4]]);

        ArrayUtils::foreach($arr, fn ($item) => $item->a = $item->a - 1);
        self::assertEquals([(object) ['a' => 0], (object) ['a' => 1], (object) ['a' => 2, 'b' => 4]], $arr);
        ArrayUtils::foreach($iter, fn ($item) => $item->a = $item->a - 1);
        self::assertEquals([(object) ['a' => 0], (object) ['a' => 1], (object) ['a' => 2, 'b' => 4]], $iter->getArrayCopy());
    }

    public function testCount(): void{
        $arr = [1, 2, 3, 4, 5];
        $iter = new \ArrayIterator($arr);

        self::assertSame(5, ArrayUtils::count($arr));
        self::assertSame(5, ArrayUtils::count($iter));
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
