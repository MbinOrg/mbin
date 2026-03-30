<?php

declare(strict_types=1);

namespace App\Pagination\Cursor;

/**
 * @template-covariant T
 * @template-covariant TCursor
 * @template-covariant TCursor2
 */
interface CursorAdapterInterface
{
    /**
     * Returns a slice of the results representing the current page of items in the list.
     *
     * @param TCursor     $cursor
     * @param TCursor2    $cursor2
     * @param int<0, max> $length
     *
     * @return iterable<array-key, T>
     */
    public function getSlice(mixed $cursor, mixed $cursor2, int $length): iterable;

    /**
     * Returns a slice of the results representing the previous page of items in reverse.
     *
     * @param TCursor     $cursor
     * @param TCursor2    $cursor2
     * @param int<0, max> $length
     *
     * @return iterable<array-key, T>
     */
    public function getPreviousSlice(mixed $cursor, mixed $cursor2, int $length): iterable;
}
