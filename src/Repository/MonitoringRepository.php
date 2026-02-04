<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\MonitoringExecutionContextFilterDto;
use App\Entity\MonitoringExecutionContext;
use App\Pagination\Pagerfanta;
use App\Pagination\QueryAdapter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MonitoringExecutionContext|null find($id, $lockMode = null, $lockVersion = null)
 * @method MonitoringExecutionContext|null findOneBy(array $criteria, array $orderBy = null)
 * @method MonitoringExecutionContext|null findOneByName(string $name)
 * @method MonitoringExecutionContext[]    findAll()
 * @method MonitoringExecutionContext[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MonitoringRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, MonitoringExecutionContext::class);
    }

    public function findByPaginated(Criteria $criteria): Pagerfanta
    {
        $qb = $this->createQueryBuilder('m')
            ->addCriteria($criteria);

        return new Pagerfanta(new QueryAdapter($qb));
    }

    /**
     * @return array{path: string, total_duration: float, query_duration: float, twig_render_duration: float, curl_request_duration: float, response_duration: float}
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function getOverviewRouteCalls(MonitoringExecutionContextFilterDto $dto, int $limit = 10): array
    {
        $criteria = $dto->toSqlWheres();
        if (null === $dto->createdFrom) {
            $criteria['whereConditions'][] = 'created_at > now() - \'30 days\'::interval';
        }
        $whereString = implode(' AND ', $criteria['whereConditions']);
        if ('mean' === $dto->chartOrdering) {
            $sql = "SELECT
                    path,
                    (SUM(duration_milliseconds) / COUNT(uuid)) as total_duration,
                    (SUM(query_duration_milliseconds) / COUNT(uuid)) as query_duration,
                    (SUM(twig_render_duration_milliseconds) / COUNT(uuid)) as twig_render_duration,
                    (SUM(curl_request_duration_milliseconds) / COUNT(uuid)) as curl_request_duration,
                    (SUM(response_sending_duration_milliseconds) / COUNT(uuid)) as response_duration
                FROM monitoring_execution_context WHERE $whereString GROUP BY path ORDER BY total_duration DESC LIMIT :limit";
        } else {
            $sql = "SELECT
                    path,
                    SUM(duration_milliseconds) as total_duration,
                    SUM(query_duration_milliseconds) as query_duration,
                    SUM(twig_render_duration_milliseconds) as twig_render_duration,
                    SUM(curl_request_duration_milliseconds) as curl_request_duration,
                    SUM(response_sending_duration_milliseconds) as response_duration
                FROM monitoring_execution_context WHERE $whereString GROUP BY path ORDER BY total_duration DESC LIMIT :limit";
        }
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);
        foreach ($criteria['parameters'] as $key => $value) {
            if (\is_array($value)) {
                $stmt->bindValue($key, $value['value'], $value['type']);
            } elseif (\is_int($value)) {
                $stmt->bindValue($key, $value, ParameterType::INTEGER);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function getFilteredContextsPaginated(MonitoringExecutionContextFilterDto $dto): Pagerfanta
    {
        $criteria = $dto->toCriteria();
        $criteria->orderBy(orderings: ['createdAt' => 'DESC']);

        return $this->findByPaginated($criteria);
    }
}
