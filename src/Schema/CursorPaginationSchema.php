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
    #[OA\Property(description: 'The secondary cursor for the current page')]
    public string $currentCursor2;
    #[OA\Property(description: 'The cursor for the next page', nullable: true)]
    public ?string $nextCursor;
    #[OA\Property(description: 'The secondary cursor for the next page', nullable: true)]
    public ?string $nextCursor2;
    #[OA\Property(description: 'The cursor for the previous page', nullable: true)]
    public ?string $previousCursor;
    #[OA\Property(description: 'The secondary cursor for the previous page', nullable: true)]
    public ?string $previousCursor2;
    #[OA\Property(description: 'Max number of items per page')]
    public int $perPage = 0;

    public function __construct(CursorPaginationInterface $pagerfanta)
    {
        $current = $pagerfanta->getCurrentCursor();
        $this->currentCursor = $this->cursorToString($current[0]);
        $this->currentCursor2 = $this->cursorToString($current[1]);
        $next = $pagerfanta->hasNextPage() ? $pagerfanta->getNextPage() : null;
        $this->nextCursor = $next ? $this->cursorToString($next[0]) : null;
        $this->nextCursor2 = $next ? $this->cursorToString($next[1]) : null;
        $previous = $pagerfanta->hasPreviousPage() ? $pagerfanta->getPreviousPage() : null;
        $this->previousCursor = $previous ? $this->cursorToString($previous[0]) : null;
        $this->previousCursor2 = $previous ? $this->cursorToString($previous[1]) : null;
        $this->perPage = $pagerfanta->getMaxPerPage();
    }

    private function cursorToString(mixed $cursor): string
    {
        if ($cursor instanceof \DateTime || $cursor instanceof \DateTimeImmutable) {
            return $cursor->format(DATE_ATOM);
        } elseif (\is_int($cursor)) {
            return ''.$cursor;
        }

        return $cursor->__toString();
    }

    public function jsonSerialize(): array
    {
        return [
            'currentCursor' => $this->currentCursor,
            'currentCursor2' => $this->currentCursor2,
            'nextCursor' => $this->nextCursor,
            'nextCursor2' => $this->nextCursor2,
            'previousCursor' => $this->previousCursor,
            'previousCursor2' => $this->previousCursor2,
            'perPage' => $this->perPage,
        ];
    }
}
