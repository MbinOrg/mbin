<?php

declare(strict_types=1);

namespace App\Pagination;

use Nette\NotImplementedException;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\PagerfantaInterface;

class EmptyPagination implements PagerfantaInterface
{
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator();
    }

    public function count(): int
    {
        return 0;
    }

    public function getAdapter(): AdapterInterface
    {
        throw new NotImplementedException();
    }

    public function setAllowOutOfRangePages(bool $allowOutOfRangePages): PagerfantaInterface
    {
        return $this;
    }

    public function getAllowOutOfRangePages(): bool
    {
        return false;
    }

    public function setNormalizeOutOfRangePages(bool $normalizeOutOfRangePages): PagerfantaInterface
    {
        return $this;
    }

    public function getNormalizeOutOfRangePages(): bool
    {
        return false;
    }

    public function setMaxPerPage(int $maxPerPage): PagerfantaInterface
    {
        return $this;
    }

    public function getMaxPerPage(): int
    {
        return 1;
    }

    public function setCurrentPage(int $currentPage): PagerfantaInterface
    {
        return $this;
    }

    public function getCurrentPage(): int
    {
        return 1;
    }

    public function getCurrentPageResults(): iterable
    {
        return [];
    }

    public function getCurrentPageOffsetStart(): int
    {
        return 0;
    }

    public function getCurrentPageOffsetEnd(): int
    {
        return 0;
    }

    public function getNbResults(): int
    {
        return 0;
    }

    public function getNbPages(): int
    {
        return 0;
    }

    public function setMaxNbPages(int $maxNbPages): PagerfantaInterface
    {
        return $this;
    }

    public function resetMaxNbPages(): PagerfantaInterface
    {
        return $this;
    }

    public function haveToPaginate(): bool
    {
        return false;
    }

    public function hasPreviousPage(): bool
    {
        return false;
    }

    public function getPreviousPage(): int
    {
        return 1;
    }

    public function hasNextPage(): bool
    {
        return false;
    }

    public function getNextPage(): int
    {
        return 1;
    }

    public function getPageNumberForItemAtPosition(int $position): int
    {
        return 1;
    }

    public function autoPagingIterator(): \Generator
    {
        yield;
    }
}
