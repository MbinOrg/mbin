<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Repository\MonitoringRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class AdminMonitoringController extends AbstractController
{
    public function __construct(
        private readonly MonitoringRepository $monitoringRepository,
        private readonly ChartBuilderInterface $chartBuilder,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function overview(#[MapQueryParameter] int $p = 1): Response
    {
        $criteria = new Criteria(orderings: ['createdAt' => 'DESC']);
        $contexts = $this->monitoringRepository->findByPaginated($criteria);
        $contexts->setCurrentPage($p);
        $contexts->setMaxPerPage(50);

        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData($this->monitoringRepository->getChartData());
        $chart->setOptions([
            'scales' => [
                'y' => [
                    'label' => '<%=value%>ms',
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'axis' => 'xy',
            ],
            'plugins' => [
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
        ]);

        return $this->render('admin/monitoring/monitoring.html.twig', [
            'executionContexts' => $contexts,
            'chart' => $chart,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    public function single(string $id, string $page, #[MapQueryParameter] bool $groupSimilar = true, #[MapQueryParameter] bool $formatQuery = false, #[MapQueryParameter] bool $showParameters = false): Response
    {
        $context = $this->monitoringRepository->findOneBy(['uuid' => $id]);
        if (!$context) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/monitoring/monitoring_single.html.twig', [
            'context' => $context,
            'page' => $page,
            'groupSimilar' => $groupSimilar,
            'formatQuery' => $formatQuery,
            'showParameters' => $showParameters,
        ]);
    }
}
