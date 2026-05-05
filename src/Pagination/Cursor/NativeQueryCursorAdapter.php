<?php

declare(strict_types=1);

namespace App\Pagination\Cursor;

use App\Pagination\Transformation\ResultTransformer;
use App\Pagination\Transformation\VoidTransformer;
use App\Utils\SqlHelpers;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * @template-covariant TCursor
 * @template-covariant TCursor2
 * @template-covariant T
 */
class NativeQueryCursorAdapter implements CursorAdapterInterface
{
    /**
     * @param string                $sql         A sql string that is expected to have a %cursor% string which will be populated by either the $forwardCursor or the $backwardCursor
     *                                           And a %cursorSort% string which will be populated by either the $forwardCursorSort or the $backwardCursorSort.
     *                                           Optionally you can also specify a secondary cursor (%cursor2%), a secondary cursor condition (%cursorCondition2%) and a secondary sort (%cursorSort2%).
     * @param array<string, string> $parameters  parameter name as key, parameter value as the value
     * @param ResultTransformer     $transformer defaults to the VoidTransformer which does not transform the result in any way
     *
     * @throws Exception
     */
    public function __construct(
        private readonly Connection $conn,
        private string $sql,
        private string $forwardCursorCondition,
        private string $backwardCursorCondition,
        private string $forwardCursorSort,
        private string $backwardCursorSort,
        private readonly array $parameters,
        private ?string $secondaryForwardCursorCondition = null,
        private ?string $secondaryBackwardCursorCondition = null,
        private ?string $secondaryForwardCursorSort = null,
        private ?string $secondaryBackwardCursorSort = null,
        private readonly ResultTransformer $transformer = new VoidTransformer(),
    ) {
    }

    /**
     * @param TCursor  $cursor
     * @param TCursor2 $cursor2
     *
     * @return iterable<array-key, T>
     *
     * @throws Exception
     */
    public function getSlice(mixed $cursor, mixed $cursor2, int $length): iterable
    {
        $replacedSql = str_replace('%cursorSort%', $this->forwardCursorSort, $this->sql);
        $replacedSql = str_replace('%cursor%', $this->forwardCursorCondition, $replacedSql);
        if ($this->secondaryForwardCursorSort) {
            $replacedSql = str_replace('%cursorSort2%', $this->secondaryForwardCursorSort, $replacedSql);
        }
        if ($this->secondaryForwardCursorCondition) {
            $replacedSql = str_replace('%cursor2%', $this->secondaryForwardCursorCondition, $replacedSql);
        }
        $sql = $replacedSql.' LIMIT :limit';

        return $this->query($sql, $cursor, $cursor2, $length);
    }

    public function getPreviousSlice(mixed $cursor, mixed $cursor2, int $length): iterable
    {
        $replacedSql = str_replace('%cursorSort%', $this->backwardCursorSort, $this->sql);
        $replacedSql = str_replace('%cursor%', $this->backwardCursorCondition, $replacedSql);
        if ($this->secondaryBackwardCursorSort) {
            $replacedSql = str_replace('%cursorSort2%', $this->secondaryBackwardCursorSort, $replacedSql);
        }
        if ($this->secondaryBackwardCursorCondition) {
            $replacedSql = str_replace('%cursor2%', $this->secondaryBackwardCursorCondition, $replacedSql);
        }
        $sql = $replacedSql.' LIMIT :limit';

        return $this->query($sql, $cursor, $cursor2, $length);
    }

    /**
     * @throws Exception
     */
    private function query(string $sql, mixed $cursor, mixed $cursor2, int $length): iterable
    {
        $statement = $this->conn->prepare($sql);
        foreach ($this->parameters as $key => $value) {
            $statement->bindValue($key, $value, SqlHelpers::getSqlType($value));
        }
        $statement->bindValue('cursor', $cursor, SqlHelpers::getSqlType($cursor));
        if (str_contains($sql, ':cursor2')) {
            $statement->bindValue('cursor2', $cursor2, SqlHelpers::getSqlType($cursor2));
        }
        $statement->bindValue('limit', $length);
        if (str_contains($sql, ':innerLimit')) {
            $statement->bindValue('innerLimit', $length * 3);
        }

        return $this->transformer->transform($statement->executeQuery()->fetchAllAssociative());
    }
}
