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
 * @template-covariant T
 */
class NativeQueryCursorAdapter implements CursorAdapterInterface
{
    /**
     * @param string                $sql         A sql string that is expected to have a %cursor% string which will be populated by either the $forwardCursor or the $backwardCursor
     *                                           And a %cursorSort% string which will be populated by either the $forwardCursorSort or the $backwardCursorSort
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
        private readonly ResultTransformer $transformer = new VoidTransformer(),
    ) {
    }

    /**
     * @param TCursor $cursor
     *
     * @return iterable<array-key, T>
     *
     * @throws Exception
     */
    public function getSlice(mixed $cursor, int $length): iterable
    {
        $replacedCursorSortSql = str_replace('%cursorSort%', $this->forwardCursorSort, $this->sql);
        $replacedCursorSql = str_replace('%cursor%', $this->forwardCursorCondition, $replacedCursorSortSql);
        $sql = $replacedCursorSql.' LIMIT :limit';

        return $this->query($sql, $cursor, $length);
    }

    public function getPreviousSlice(mixed $cursor, int $length): iterable
    {
        $replacedCursorSortSql = str_replace('%cursorSort%', $this->backwardCursorSort, $this->sql);
        $replacedCursorSql = str_replace('%cursor%', $this->backwardCursorCondition, $replacedCursorSortSql);
        $sql = $replacedCursorSql.' LIMIT :limit';

        return $this->query($sql, $cursor, $length);
    }

    /**
     * @throws Exception
     */
    private function query(string $sql, mixed $cursor, int $length): iterable
    {
        $statement = $this->conn->prepare($sql);
        foreach ($this->parameters as $key => $value) {
            $statement->bindValue($key, $value, SqlHelpers::getSqlType($value));
        }
        $statement->bindValue('cursor', $cursor, SqlHelpers::getSqlType($cursor));
        $statement->bindValue('limit', $length);

        return $this->transformer->transform($statement->executeQuery()->fetchAllAssociative());
    }
}
