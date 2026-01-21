<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\DTO\MonitoringExecutionContextFilterDto;
use App\Form\MonitoringExecutionContextFilterType;
use App\Repository\MonitoringRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class AdminMonitoringController extends AbstractController
{
    public function __construct(
        private readonly MonitoringRepository $monitoringRepository,
        private readonly ChartBuilderInterface $chartBuilder,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function overview(Request $request, #[MapQueryParameter] int $p = 1): Response
    {
        $dto = new MonitoringExecutionContextFilterDto();
        $form = $this->formFactory->createNamed('filter', MonitoringExecutionContextFilterType::class, $dto, ['method' => 'GET']);
        $hideChart = false;

        try {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $dto = $form->getData();
                $hideChart = true;
            }
        } catch (\Exception) {
        }

        $contexts = $this->monitoringRepository->getFilteredContextsPaginated($dto);
        $contexts->setCurrentPage($p);
        $contexts->setMaxPerPage(50);

        if (1 === $p && !$hideChart) {
            $chart = $this->getOverViewChart();
        }

        return $this->render('admin/monitoring/monitoring.html.twig', [
            'page' => $p,
            'executionContexts' => $contexts,
            'chart' => $chart ?? null,
            'form' => $form,
        ]);
    }

    private function getOverViewChart(): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData($this->getOverviewChartData());
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

        return $chart;
    }

    public function getOverviewChartData(): array
    {
        $rawData = $this->monitoringRepository->getOverviewRouteCalls();
        $labels = [];
        $overallDurationRemaining = [];
        $queryDurations = [];
        $twigRenderDuration = [];
        $curlRequestDuration = [];
        $sendingDuration = [];

        foreach ($rawData as $data) {
            $labels[] = $data['path'];
            $total = round(\floatval($data['total_duration']), 2);
            $query = round(\floatval($data['query_duration']), 2);
            $twig = round(\floatval($data['twig_render_duration']), 2);
            $curl = round(\floatval($data['curl_request_duration']), 2);
            $sending = round(\floatval($data['response_duration']), 2);
            $overallDurationRemaining[] = max(0, round($total - $query - $twig - $curl - $sending, 2));
            $queryDurations[] = $query;
            $twigRenderDuration[] = $twig;
            $curlRequestDuration[] = $curl;
            $sendingDuration[] = $sending;
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
                    'data' => $twigRenderDuration,
                    'stack' => '1',
                    'backgroundColor' => 'green',
                    'borderRadius' => 5,
                ],
                [
                    'label' => $this->translator->trans('monitoring_duration_curl_request'),
                    'data' => $curlRequestDuration,
                    'stack' => '1',
                    'backgroundColor' => '#07abaf',
                    'borderRadius' => 5,
                ],
                [
                    'label' => $this->translator->trans('monitoring_duration_sending_response'),
                    'data' => $sendingDuration,
                    'stack' => '1',
                    'backgroundColor' => 'lightgray',
                    'borderRadius' => 5,
                ],
            ],
        ];
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
