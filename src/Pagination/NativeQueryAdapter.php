<?php

declare(strict_types=1);

namespace App\Pagination;

use App\Pagination\Transformation\ResultTransformer;
use App\Pagination\Transformation\VoidTransformer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Types;
use Pagerfanta\Adapter\AdapterInterface;

/**
 * This adapter only works if your sql does not define an :offset and a :limit parameter. These will be appended.
 */
class NativeQueryAdapter implements AdapterInterface
{
    private Statement $statement;

    /**
     * @param int|null          $numOfResults if this is null, then a query will be executed to get the number of results
     * @param ResultTransformer $transformer  defaults to the VoidTransformer which does not transform the result in any way
     *
     * @throws Exception
     */
    public function __construct(
        private readonly Connection $conn,
        string $sql,
        private readonly array $parameters,
        private ?int $numOfResults = null,
        private readonly ResultTransformer $transformer = new VoidTransformer(),
    ) {
        if (null === $this->numOfResults) {
            $sql2 = 'SELECT COUNT(*) as cnt FROM ('.$sql.') sub';
            $stmt2 = $this->conn->prepare($sql2);
            foreach ($this->parameters as $key => $value) {
                $stmt2->bindValue($key, $value, $this->getSqlType($value));
            }
            $result = $stmt2->executeQuery()->fetchAllAssociative();
            $this->numOfResults = $result[0]['cnt'];
        }

        $this->statement = $this->conn->prepare($sql.' LIMIT :limit OFFSET :offset');
        foreach ($this->parameters as $key => $value) {
            $this->statement->bindValue($key, $value, $this->getSqlType($value));
        }
    }

    public function getNbResults(): int
    {
        return $this->numOfResults;
    }

    public function getSlice(int $offset, int $length): iterable
    {
        $this->statement->bindValue('offset', $offset);
        $this->statement->bindValue('limit', $length);

        return $this->transformer->transform($this->statement->executeQuery()->fetchAllAssociative());
    }

    private function getSqlType(mixed $value): mixed
    {
        if ($value instanceof \DateTimeImmutable) {
            return Types::DATETIMETZ_IMMUTABLE;
        } elseif ($value instanceof \DateTime) {
            return Types::DATETIMETZ_MUTABLE;
        }

        return ParameterType::STRING;
    }
}
