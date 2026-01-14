<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MonitoringExecutionContext;
use App\Pagination\Pagerfanta;
use App\Pagination\QueryAdapter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($registry, MonitoringExecutionContext::class);
    }

    public function findByPaginated(\Doctrine\Common\Collections\Criteria $criteria): Pagerfanta
    {
        $qb = $this->createQueryBuilder('m')
            ->addCriteria($criteria);

        return new Pagerfanta(new QueryAdapter($qb));
    }

    /**
     * @var int
     *
     * @return array{path: string, total_duration: float, query_duration: float, twig_render_duration: float, curl_request_duration: float}
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
                    SUM(curl_request_duration_milliseconds) as curl_request_duration
                FROM monitoring_execution_context WHERE created_at > now() - '30 days'::interval GROUP BY path ORDER BY total_duration DESC LIMIT :limit";
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function getChartData(): array
    {
        $rawData = $this->getOverviewRouteCalls();
        $labels = [];
        $overallDurationRemaining = [];
        $queryDurations = [];
        $twigRenderDurationRemaining = [];
        $curlRequestDurationRemaining = [];

        foreach ($rawData as $data) {
            $labels[] = $data['path'];
            $total = round(\floatval($data['total_duration']), 2);
            $query = round(\floatval($data['query_duration']), 2);
            $twig = round(\floatval($data['twig_render_duration']), 2);
            $curl = round(\floatval($data['curl_request_duration']), 2);
            $overallDurationRemaining[] = max(0, round($total - $query - $twig - $curl, 2));
            $queryDurations[] = $query;
            $twigRenderDurationRemaining[] = $twig;
            $curlRequestDurationRemaining[] = $curl;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->translator->trans('monitoring_duration_overall'),
                    'data' => $overallDurationRemaining,
                    'stack' => '1',
                    'backgroundColor' => 'gray',
                    'borderRadius' => 5,
                ],
                [
                    'label' => $this->translator->trans('monitoring_duration_query'),
                    'data' => $queryDurations,
                    'stack' => '1',
                    'backgroundColor' => '#a3067c',
                    'borderRadius' => 5,
                ],
                [
                    'label' => $this->translator->trans('monitoring_duration_twig_render'),
                    'data' => $twigRenderDurationRemaining,
                    'stack' => '1',
                    'backgroundColor' => 'green',
                    'borderRadius' => 5,
                ],
                [
                    'label' => $this->translator->trans('monitoring_duration_curl_request'),
                    'data' => $curlRequestDurationRemaining,
                    'stack' => '1',
                    'backgroundColor' => '#07abaf',
                    'borderRadius' => 5,
                ],
            ],
        ];
    }
}
