<?php

declare(strict_types=1);

namespace App\Utils;

class ArrayUtils
{
    public static function numCompareAscending(int $a, int $b): int
    {
        if ($a === $b) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    }

    public static function numCompareDescending(int $a, int $b): int
    {
        if ($a === $b) {
            return 0;
        }

        return ($a < $b) ? 1 : -1;
    }

    /**
     * @template-covariant T
     *
     * @param T[] $a
     *
     * @return T[][]
     */
    public static function sliceArrayIntoEqualPieces(array $a, int $size): array
    {
        $arraySize = \sizeof($a);
        $steps = $arraySize / $size;
        $result = [];
        for ($i = 0; $i < $steps; ++$i) {
            $result[] = \array_slice($a, $i * $size, $size);
        }

        return $result;
    }

    public static function filter(iterable $arr, callable $callback, int $mode = 0): array
    {
        if(gettype($arr) !== 'array') {
            $arr = iterator_to_array($arr);
        }
        /** @var array $arr */
        return array_filter($arr, $callback, $mode);
    }

    public static function map(iterable $arr, callable $callback): array
    {
        if(gettype($arr) !== 'array') {
            $arr = iterator_to_array($arr);
        }
        /** @var array $arr */
        return array_map($callback, $arr);
    }

    public static function foreach(iterable $arr, callable $callback): void
    {
        foreach ($arr as $item) {
            $callback($item);
        }
    }

    public static function count(iterable $arr): int
    {
        return iterator_count($arr);
    }
}
