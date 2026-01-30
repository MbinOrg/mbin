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
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;

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
        private readonly ?CacheInterface $cache = null,
    ) {
        if (null === $this->numOfResults) {
            $this->numOfResults = $this->calculateNumOfResultsCached($sql, $this->parameters);
        }

        $this->statement = $this->conn->prepare($sql.' LIMIT :limit OFFSET :offset');
        foreach ($this->parameters as $key => $value) {
            $this->statement->bindValue($key, $value, $this->getSqlType($value));
        }
    }

    private function calculateNumOfResultsCached(string $sql, array $parameters): int
    {
        if (null === $this->cache) {
            return $this->calculateNumOfResults($sql, $parameters);
        }
        $sqlHash = hash('sha256', $sql);
        $parameterHash = hash('sha256', print_r($parameters, true));

        return $this->cache->get("native_query_count_$sqlHash-$parameterHash", function (CacheItemInterface $item) use ($sql, $parameters) {
            $count = $this->calculateNumOfResults($sql, $parameters);
            if ($count > 25000) {
                $item->expiresAfter(new \DateInterval('PT6H'));
            } elseif ($count > 10000) {
                $item->expiresAfter(new \DateInterval('PT1H'));
            } elseif ($count > 1000) {
                $item->expiresAfter(new \DateInterval('PT10M'));
            }

            return $count;
        });
    }

    private function calculateNumOfResults(string $sql, array $parameters): int
    {
        $sql2 = 'SELECT COUNT(*) as cnt FROM ('.$sql.') sub';
        $stmt2 = $this->conn->prepare($sql2);
        foreach ($parameters as $key => $value) {
            $stmt2->bindValue($key, $value, $this->getSqlType($value));
        }
        $result = $stmt2->executeQuery()->fetchAllAssociative();

        return $result[0]['cnt'];
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
        } elseif (\is_int($value)) {
            return Types::INTEGER;
        }

        return ParameterType::STRING;
    }
}
