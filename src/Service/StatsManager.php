<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\StatsContentRepository;
use App\Repository\StatsRepository;
use App\Repository\StatsVotesRepository;
use App\Service\SettingsManager;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class StatsManager
{
    public function __construct(
        private readonly StatsVotesRepository $votesRepository,
        private readonly StatsContentRepository $contentRepository,
        private readonly ChartBuilderInterface $chartBuilder,
        private readonly SettingsManager $settingsManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function drawMonthlyContentChart(?User $user = null, ?Magazine $magazine = null, ?bool $onlyLocal = null): Chart
    {
        $stats = $this->contentRepository->getOverallStats($user, $magazine, $onlyLocal);

        $labels = array_map(fn ($val) => ($val['month'] < 10 ? '0' : '').$val['month'].'/'.$val['year'],
            $stats['entries']);

        return $this->createGeneralDataset($stats, $labels);
    }

    private function createGeneralDataset(array $stats, array $labels): Chart
    {
        $dataset = [
            [
                'label' => $this->translator->trans('threads'),
                'borderColor' => '#4382AD',
                'data' => array_map(fn ($val) => $val['count'], $stats['entries']),
            ],
            [
                'label' => $this->translator->trans('comments'),
                'borderColor' => '#6253ac',
                'data' => array_map(fn ($val) => $val['count'], $stats['comments']),
            ],
            [
                'label' => $this->translator->trans('posts'),
                'borderColor' => '#ac5353',
                'data' => array_map(fn ($val) => $val['count'], $stats['posts']),
            ],
            [
                'label' => $this->translator->trans('replies'),
                'borderColor' => '#09a084',
                'data' => array_map(fn ($val) => $val['count'], $stats['replies']),
            ],
        ];

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        return $chart->setData([
            'labels' => $labels,
            'datasets' => $dataset,
        ]);
    }

    public function drawDailyContentStatsByTime(\DateTime $start, ?User $user = null, ?Magazine $magazine = null, ?bool $onlyLocal = null): Chart
    {
        $stats = $this->contentRepository->getStatsByTime($start, $user, $magazine, $onlyLocal);

        $labels = array_map(fn ($val) => $val['day']->format('Y-m-d'), $stats['entries']);

        return $this->createGeneralDataset($stats, $labels);
    }

    public function drawMonthlyVotesChart(?User $user = null, ?Magazine $magazine = null, ?bool $onlyLocal = null): Chart
    {
        $stats = $this->votesRepository->getOverallStats($user, $magazine, $onlyLocal);

        $labels = array_map(fn ($val) => ($val['month'] < 10 ? '0' : '').$val['month'].'/'.$val['year'],
            $stats['entries']);

        return $this->createVotesDataset($stats, $labels);
    }

    private function createVotesDataset(array $stats, array $labels): Chart
    {
        $results = [];
        foreach ($stats['entries'] as $index => $entry) {
            $entry['up'] = array_sum(array_map(fn ($type) => $type[$index]['up'], $stats));
            $entry['down'] = 'disabled' !== $this->settingsManager->get('MBIN_DOWNVOTES_MODE') ? 0 : array_sum(array_map(fn ($type) => $type[$index]['down'], $stats));
            $entry['boost'] = array_sum(array_map(fn ($type) => $type[$index]['boost'], $stats));

            $results[] = $entry;
        }

        $dataset = [
            [
                'label' => $this->translator->trans('up_votes'),
                'borderColor' => '#92924c',
                'data' => array_map(fn ($val) => $val['boost'], $results),
            ],
            [
                'label' => $this->translator->trans('favourites'),
                'borderColor' => '#3c5211',
                'data' => array_map(fn ($val) => $val['up'], $results),
            ],
            [
                'label' => $this->translator->trans('down_votes'),
                'borderColor' => '#8f0b00',
                'data' => 'disabled' !== $this->settingsManager->get('MBIN_DOWNVOTES_MODE') ? [] : array_map(fn ($val) => $val['down'], $results),
            ],
        ];

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        return $chart->setData([
            'labels' => $labels,
            'datasets' => $dataset,
        ]);
    }

    public function drawDailyVotesStatsByTime(\DateTime $start, ?User $user = null, ?Magazine $magazine = null, ?bool $onlyLocal = null): Chart
    {
        $stats = $this->votesRepository->getStatsByTime($start, $user, $magazine, $onlyLocal);

        $labels = array_map(fn ($val) => $val['day']->format('Y-m-d'), $stats['entries']);

        return $this->createVotesDataset($stats, $labels);
    }

    public function resolveType(?string $value, ?string $default = null): string
    {
        $routes = [
            'general' => StatsRepository::TYPE_GENERAL,
            'content' => StatsRepository::TYPE_CONTENT,
            'votes' => StatsRepository::TYPE_VOTES,
        ];

        return $routes[$value] ?? $routes[$default ?? StatsRepository::TYPE_GENERAL];
    }
}
