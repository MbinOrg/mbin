<?php

declare(strict_types=1);

namespace App\Schema;

use App\Pagination\Cursor\CursorPaginationInterface;
use OpenApi\Attributes as OA;

#[OA\Schema()]
class CursorPaginationSchema implements \JsonSerializable
{
    #[OA\Property(description: 'The cursor for the current page')]
    public string $currentCursor;
    #[OA\Property(description: 'The cursor for the next page', nullable: true)]
    public ?string $nextCursor;
    #[OA\Property(description: 'The cursor for the previous page', nullable: true)]
    public ?string $previousCursor;
    #[OA\Property(description: 'Max number of items per page')]
    public int $perPage = 0;

    public function __construct(CursorPaginationInterface $pagerfanta)
    {
        $this->currentCursor = $this->cursorToString($pagerfanta->getCurrentCursor());
        $this->nextCursor = $pagerfanta->hasNextPage() ? $this->cursorToString($pagerfanta->getNextPage()) : null;
        $this->previousCursor = $pagerfanta->hasPreviousPage() ? $this->cursorToString($pagerfanta->getPreviousPage()) : null;
        $this->perPage = $pagerfanta->getMaxPerPage();
    }

    private function cursorToString(mixed $cursor): string
    {
        if ($cursor instanceof \DateTime || $cursor instanceof \DateTimeImmutable) {
            return $cursor->format(DATE_ATOM);
        }

        return $cursor->__toString();
    }

    public function jsonSerialize(): mixed
    {
        return [
            'currentCursor' => $this->currentCursor,
            'nextCursor' => $this->nextCursor,
            'previousCursor' => $this->previousCursor,
            'perPage' => $this->perPage,
        ];
    }
}
