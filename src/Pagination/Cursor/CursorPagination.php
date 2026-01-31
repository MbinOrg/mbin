<?php

declare(strict_types=1);

namespace App\Pagination\Cursor;

/**
 * @template-covariant TCursor
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
     * @var TCursor|null
     */
    private mixed $nextCursor = null;

    /**
     * @param CursorAdapterInterface<TCursor> $adapter
     */
    public function __construct(
        private readonly CursorAdapterInterface $adapter,
        private readonly string $cursorFieldName,
        private int $maxPerPage,
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
        $results = $this->adapter->getSlice($this->currentCursor, $this->maxPerPage);
        $this->currentPageResults = [...$results];

        return $this->currentPageResults;
    }

    public function haveToPaginate(): bool
    {
        return $this->maxPerPage === \sizeof($this->currentPageResults ?? [...$this->getCurrentPageResults()]);
    }

    public function hasNextPage(): bool
    {
        return $this->haveToPaginate();
    }

    public function getNextPage(): mixed
    {
        if (null !== $this->nextCursor) {
            return $this->nextCursor;
        }

        $cursorFieldName = $this->cursorFieldName;
        $array = $this->getCurrentPageResults();
        $nextCursor = null;
        $i = 0;
        foreach ($array as $item) {
            if (\is_object($item)) {
                $nextCursor = $item->$cursorFieldName;
            } elseif (\is_array($item)) {
                $nextCursor = $item[$cursorFieldName];
            } else {
                throw new \LogicException('Item has to be an object or array.');
            }
            ++$i;
        }
        if ($this->maxPerPage === $i) {
            $this->nextCursor = $nextCursor;

            return $nextCursor;
        }
        throw new \LogicException('There is no next page');
    }

    /**
     * Generates an iterator to automatically iterate over all pages in a result set.
     *
     * @return \Generator<int, T, mixed, void>
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

            $this->setCurrentPage($this->getNextPage());
        }
    }

    public function setCurrentPage(mixed $cursor): CursorPaginationInterface
    {
        if ($cursor !== $this->currentCursor) {
            $this->previousPageResults = null;
            $this->currentCursor = $cursor;
            $this->currentPageResults = null;
            $this->nextCursor = null;
        }

        return $this;
    }

    public function getCurrentCursor(): mixed
    {
        return $this->currentCursor;
    }

    public function hasPreviousPage(): bool
    {
        return \sizeof($this->getPreviousPageResults()) > 0;
    }

    public function getPreviousPage(): mixed
    {
        $cursorFieldName = $this->cursorFieldName;
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

        $currentCursor = $this->getCurrentCursor();
        // we need to modify the value to include the last result of the previous page in reverse,
        // otherwise we will always be missing one result when going back
        if ($currentCursor > $cursor) {
            if ($cursor instanceof \DateTime || $cursor instanceof \DateTimeImmutable) {
                return (new \DateTimeImmutable())->setTimestamp($cursor->getTimestamp() - 1);
            } elseif (\is_int($cursor)) {
                return --$cursor;
            }
        } else {
            if ($cursor instanceof \DateTime || $cursor instanceof \DateTimeImmutable) {
                return (new \DateTimeImmutable())->setTimestamp($cursor->getTimestamp() + 1);
            } elseif (\is_int($cursor)) {
                return ++$cursor;
            }
        }

        return $cursor;
    }

    private function getPreviousPageResults(): array
    {
        if (null === $this->previousPageResults) {
            $this->previousPageResults = [...$this->adapter->getPreviousSlice($this->currentCursor, $this->maxPerPage)];
        }

        return $this->previousPageResults;
    }
}
