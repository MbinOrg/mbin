<?php

declare(strict_types=1);

namespace App\Pagination\Cursor;

/**
 * @template-covariant TCursor
 * @template-covariant TCursor2
 * @template-covariant TValue
 */
class CursorPagination implements CursorPaginationInterface
{
    /**
     * @var array<TValue>|null
     */
    private ?array $currentPageResults = null;

    /**
     * @var array<TValue>|null
     */
    private ?array $previousPageResults = null;

    /**
     * @var TCursor|null
     */
    private mixed $currentCursor = null;

    /**
     * @var TCursor2|null
     */
    private mixed $currentCursor2 = null;

    /**
     * @var TCursor|null
     */
    private mixed $nextCursor = null;

    /**
     * @var TCursor2|null
     */
    private mixed $nextCursor2 = null;

    /**
     * @param CursorAdapterInterface<TCursor, TCursor2> $adapter
     * @param ?string                                   $cursor2FieldName  If set the pagination will assume that the adapter uses a secondary cursor
     * @param ?mixed                                    $cursor2LowerLimit The lower limit of the secondary cursor, if it is an integer field 0 is the default
     */
    public function __construct(
        private readonly CursorAdapterInterface $adapter,
        private readonly string $cursorFieldName,
        private int $maxPerPage,
        private readonly ?string $cursor2FieldName = null,
        private readonly mixed $cursor2LowerLimit = 0,
    ) {
    }

    public function getIterator(): \Traversable
    {
        $results = $this->getCurrentPageResults();

        if ($results instanceof \Iterator) {
            return $results;
        }

        if ($results instanceof \IteratorAggregate) {
            return $results->getIterator();
        }

        if (\is_array($results)) {
            return new \ArrayIterator($results);
        }

        throw new \InvalidArgumentException(\sprintf('Cannot create iterator with page results of type "%s".', \get_class($results)));
    }

    public function getAdapter(): CursorAdapterInterface
    {
        return $this->adapter;
    }

    public function setMaxPerPage(int $maxPerPage): CursorPaginationInterface
    {
        $this->maxPerPage = $maxPerPage;

        return $this;
    }

    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    public function getCurrentPageResults(): iterable
    {
        if (null !== $this->currentPageResults) {
            return $this->currentPageResults;
        }
        $results = $this->adapter->getSlice($this->currentCursor, $this->currentCursor2, $this->maxPerPage);
        $this->currentPageResults = [...$results];

        return $this->currentPageResults;
    }

    public function haveToPaginate(): bool
    {
        return $this->hasNextPage() || $this->hasPreviousPage();
    }

    public function hasNextPage(): bool
    {
        return $this->maxPerPage === \sizeof($this->currentPageResults ?? [...$this->getCurrentPageResults()]);
    }

    /**
     * @return array{0: TCursor, 1: TCursor2}
     */
    public function getNextPage(): array
    {
        if (null !== $this->nextCursor) {
            return $this->nextCursor;
        }

        $cursorFieldName = $this->cursorFieldName;
        $cursor2FieldName = $this->cursor2FieldName;
        $array = $this->getCurrentPageResults();
        $nextCursor = null;
        $nextCursor2 = null;
        $i = 0;
        foreach ($array as $item) {
            if (\is_object($item)) {
                $nextCursor = $item->$cursorFieldName;
            } elseif (\is_array($item)) {
                $nextCursor = $item[$cursorFieldName];
            } else {
                throw new \LogicException('Item has to be an object or array.');
            }
            if (null !== $cursor2FieldName) {
                if (\is_object($item)) {
                    $nextCursor2 = $item->$cursor2FieldName;
                } elseif (\is_array($item)) {
                    $nextCursor2 = $item[$cursor2FieldName];
                } else {
                    throw new \LogicException('Item has to be an object or array.');
                }
            }
            ++$i;
        }
        if ($this->maxPerPage === $i) {
            $this->nextCursor = $nextCursor;
            if (null !== $this->nextCursor2) {
                $this->nextCursor2 = $nextCursor2;
            }

            return [$nextCursor, $nextCursor2];
        }
        throw new \LogicException('There is no next page');
    }

    /**
     * Generates an iterator to automatically iterate over all pages in a result set.
     *
     * @return \Generator<int, TValue, mixed, void>
     */
    public function autoPagingIterator(): \Generator
    {
        while (true) {
            foreach ($this->getCurrentPageResults() as $item) {
                yield $item;
            }

            if (!$this->hasNextPage()) {
                break;
            }

            $nextCursors = $this->getNextPage();
            $this->setCurrentPage($nextCursors[0], $nextCursors[1]);
        }
    }

    public function setCurrentPage(mixed $cursor, mixed $cursor2 = null): CursorPaginationInterface
    {
        if ($cursor !== $this->currentCursor || $cursor2 !== $this->currentCursor2) {
            $this->previousPageResults = null;
            $this->currentCursor = $cursor;
            $this->currentCursor2 = $cursor2;
            $this->currentPageResults = null;
            $this->nextCursor = null;
            $this->nextCursor2 = null;
        }

        return $this;
    }

    public function getCurrentCursor(): array
    {
        return [$this->currentCursor, $this->currentCursor2];
    }

    public function hasPreviousPage(): bool
    {
        return \sizeof($this->getPreviousPageResults()) > 0;
    }

    /**
     * @return array{0: TCursor, 1: TCursor2}
     */
    public function getPreviousPage(): array
    {
        $cursorFieldName = $this->cursorFieldName;
        $cursor2FieldName = $this->cursor2FieldName;
        $array = $this->getPreviousPageResults();
        $key = array_key_last($array);

        $item = $array[$key];
        if (\is_object($item)) {
            $cursor = $item->$cursorFieldName;
        } elseif (\is_array($item)) {
            $cursor = $item[$cursorFieldName];
        } else {
            throw new \LogicException('Item has to be an object or array.');
        }

        if (null !== $cursor2FieldName) {
            if (\is_object($item)) {
                $cursor2 = $item->$cursor2FieldName;
            } elseif (\is_array($item)) {
                $cursor2 = $item[$cursor2FieldName];
            } else {
                throw new \LogicException('Item has to be an object or array.');
            }
        }

        $currentCursors = $this->getCurrentCursor();

        return $this->getPreviousCursors($currentCursors[0], $cursor, $currentCursors[1], $cursor2 ?? null);
    }

    private function getPreviousPageResults(): array
    {
        if (null === $this->previousPageResults) {
            $this->previousPageResults = [...$this->adapter->getPreviousSlice($this->currentCursor, $this->currentCursor2, $this->maxPerPage)];
        }

        return $this->previousPageResults;
    }

    /**
     * @return array{0: \DateTimeImmutable|int|mixed, 1: \DateTimeImmutable|int|mixed}
     */
    private function getPreviousCursors(mixed $currentCursor, mixed $cursor, mixed $currentCursor2, mixed $cursor2): array
    {
        // we need to modify the value to include the last result of the previous page in reverse,
        // otherwise we will always be missing one result when going back
        if (null === $currentCursor2) {
            if ($currentCursor > $cursor) {
                return [$this->decreaseCursor($cursor), null];
            } else {
                return [$this->increaseCursor($cursor), null];
            }
        } else {
            if ($cursor2 > $this->cursor2LowerLimit) {
                if ($currentCursor2 > $cursor2) {
                    return [$cursor, $this->decreaseCursor($cursor2)];
                } else {
                    return [$cursor, $this->increaseCursor($cursor2)];
                }
            } else {
                if ($currentCursor > $cursor) {
                    return [$this->decreaseCursor($cursor), $this->cursor2LowerLimit];
                } else {
                    return [$this->increaseCursor($cursor), $this->cursor2LowerLimit];
                }
            }
        }
    }

    private function decreaseCursor(mixed $cursor): mixed
    {
        if ($cursor instanceof \DateTime || $cursor instanceof \DateTimeImmutable) {
            return (new \DateTimeImmutable())->setTimestamp($cursor->getTimestamp() - 1);
        } elseif (\is_int($cursor)) {
            return --$cursor;
        }

        return $cursor;
    }

    private function increaseCursor(mixed $cursor): mixed
    {
        if ($cursor instanceof \DateTime || $cursor instanceof \DateTimeImmutable) {
            return (new \DateTimeImmutable())->setTimestamp($cursor->getTimestamp() + 1);
        } elseif (\is_int($cursor)) {
            return ++$cursor;
        }

        return $cursor;
    }
}
