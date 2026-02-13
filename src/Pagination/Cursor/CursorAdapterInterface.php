<?php

declare(strict_types=1);

namespace App\Pagination\Cursor;

/**
 * @template-covariant T
 * @template-covariant TCursor
 */
interface CursorAdapterInterface
{
    /**
     * Returns a slice of the results representing the current page of items in the list.
     *
     * @param TCursor     $cursor
     * @param int<0, max> $length
     *
     * @return iterable<array-key, T>
     */
    public function getSlice(mixed $cursor, int $length): iterable;

    /**
     * Returns a slice of the results representing the previous page of items in reverse.
     *
     * @param TCursor     $cursor
     * @param int<0, max> $length
     *
     * @return iterable<array-key, T>
     */
    public function getPreviousSlice(mixed $cursor, int $length): iterable;
}
