<?php

declare(strict_types=1);

namespace App\Pagination\Cursor;

use App\Pagination\T;
use Pagerfanta\Exception\LogicException;

/**
 * @template-covariant T
 * @template-covariant TCursor
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
     * @param TCursor $cursor
     */
    public function setCurrentPage(mixed $cursor): self;

    public function getMaxPerPage(): int;

    /**
     * @return iterable<array-key, T>
     */
    public function getCurrentPageResults(): iterable;

    public function haveToPaginate(): bool;

    public function hasNextPage(): bool;

    /**
     * @return TCursor
     *
     * @throws LogicException if there is no next page
     */
    public function getNextPage(): mixed;

    public function hasPreviousPage(): bool;

    /**
     * @return TCursor
     *
     * @throws LogicException if there is no previous page
     */
    public function getPreviousPage(): mixed;

    /**
     * @return TCursor
     */
    public function getCurrentCursor(): mixed;
}
