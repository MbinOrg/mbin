<?php

declare(strict_types=1);

namespace App\Pagination\Cursor;

use Pagerfanta\Exception\LogicException;

/**
 * @template-covariant T
 * @template-covariant TCursor
 * @template-covariant TCursor2
 *
 * @extends \IteratorAggregate<T>
 *
 * @method \Generator<int, T, mixed, void> autoPagingIterator()
 */
interface CursorPaginationInterface extends \IteratorAggregate
{
    /**
     * @return CursorAdapterInterface<T>
     */
    public function getAdapter(): CursorAdapterInterface;

    public function setMaxPerPage(int $maxPerPage): self;

    /**
     * @param TCursor  $cursor
     * @param TCursor2 $cursor2
     */
    public function setCurrentPage(mixed $cursor, mixed $cursor2 = null): self;

    public function getMaxPerPage(): int;

    /**
     * @return iterable<array-key, T>
     */
    public function getCurrentPageResults(): iterable;

    public function haveToPaginate(): bool;

    public function hasNextPage(): bool;

    /**
     * @return array{0:TCursor, 1:TCursor2}
     *
     * @throws LogicException if there is no next page
     */
    public function getNextPage(): array;

    public function hasPreviousPage(): bool;

    /**
     * @return array{0:TCursor, 1:TCursor2}
     *
     * @throws LogicException if there is no previous page
     */
    public function getPreviousPage(): array;

    /**
     * @return array{0:TCursor, 1:TCursor2}
     */
    public function getCurrentCursor(): array;
}
