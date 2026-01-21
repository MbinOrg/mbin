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
     * @var int
     *
     * @return array{path: string, total_duration: float, query_duration: float, twig_render_duration: float, curl_request_duration: float, response_duration: float}
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function getOverviewRouteCalls(int $limit = 10): array
    {
        $sql = "SELECT
                    path,
                    SUM(duration_milliseconds) as total_duration,
                    SUM(query_duration_milliseconds) as query_duration,
                    SUM(twig_render_duration_milliseconds) as twig_render_duration,
                    SUM(curl_request_duration_milliseconds) as curl_request_duration,
                    SUM(response_sending_duration_milliseconds) as response_duration
                FROM monitoring_execution_context WHERE created_at > now() - '30 days'::interval GROUP BY path ORDER BY total_duration DESC LIMIT :limit";
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function getFilteredContextsPaginated(MonitoringExecutionContextFilterDto $dto): Pagerfanta
    {
        $criteria = new Criteria(orderings: ['createdAt' => 'DESC']);
        if (null !== $dto->executionType) {
            $criteria->andWhere(Criteria::expr()->eq('executionType', $dto->executionType));
        }
        if (null !== $dto->userType) {
            $criteria->andWhere(Criteria::expr()->eq('userType', $dto->userType));
        }
        if (null !== $dto->path) {
            $criteria->andWhere(Criteria::expr()->eq('path', $dto->path));
        }
        if (null !== $dto->handler) {
            $criteria->andWhere(Criteria::expr()->eq('handler', $dto->handler));
        }
        if (null !== $dto->hasException) {
            if ($dto->hasException) {
                $criteria->andWhere(Criteria::expr()->isNotNull('exception'));
            } else {
                $criteria->andWhere(Criteria::expr()->isNull('exception'));
            }
        }
        if (null !== $dto->durationMinimum) {
            $criteria->andWhere(Criteria::expr()->gt('durationMilliseconds', $dto->durationMinimum));
        }
        if (null !== $dto->createdFrom) {
            $criteria->andWhere(Criteria::expr()->gt('createdAt', $dto->createdFrom));
        }
        if (null !== $dto->createdTo) {
            $criteria->andWhere(Criteria::expr()->lt('createdAt', $dto->createdTo));
        }

        return $this->findByPaginated($criteria);
    }
}
