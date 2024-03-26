<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Magazine;
use App\Entity\Site;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

abstract class StatsRepository extends ServiceEntityRepository
{
    public const TYPE_GENERAL = 'general';
    public const TYPE_CONTENT = 'content';
    public const TYPE_VIEWS = 'views';
    public const TYPE_VOTES = 'votes';

    protected ?\DateTime $start;
    protected ?User $user;
    protected ?Magazine $magazine;
    protected ?bool $onlyLocal;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    protected function sort(array $results): array
    {
        usort($results, fn ($a, $b): int => [$a['year'], $a['month']]
            <=>
            [$b['year'], $b['month']]
        );

        return $results;
    }

    protected function prepareContentDaily(array $entries): array
    {
        $to = new \DateTime();
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($this->start, $interval, $to);

        $results = [];
        foreach ($period as $d) {
            $existed = array_filter(
                $entries,
                fn ($entry) => (new \DateTime($entry['day']))->format('Y-m-d') === $d->format('Y-m-d')
            );

            if (!empty($existed)) {
                $existed = current($existed);
                $existed['day'] = new \DateTime($existed['day']);

                $results[] = $existed;
                continue;
            }

            $results[] = [
                'day' => $d,
                'count' => 0,
            ];
        }

        return $results;
    }

    protected function prepareContentOverall(array $entries, int $startYear, int $startMonth): array
    {
        $currentMonth = (int) (new \DateTime('now'))->format('n');
        $currentYear = (int) (new \DateTime('now'))->format('Y');

        $results = [];
        for ($y = $startYear; $y <= $currentYear; ++$y) {
            for ($m = 1; $m <= 12; ++$m) {
                if ($y === $currentYear && $m > $currentMonth) {
                    break;
                }

                if ($y === $startYear && $m < $startMonth) {
                    continue;
                }

                $existed = array_filter($entries, fn ($entry) => $entry['month'] === $m && (int) $entry['year'] === $y);

                if (!empty($existed)) {
                    $results[] = current($existed);
                    continue;
                }

                $results[] = [
                    'month' => $m,
                    'year' => $y,
                    'count' => 0,
                ];
            }
        }

        return $results;
    }

    protected function getStartDate(array $values): array
    {
        return array_map(fn ($val) => ['year' => $val['year'], 'month' => $val['month']], $values);
    }

    protected function prepareContentReturn(array $entries, array $comments, array $posts, array $replies): array
    {
        $startDate = $this->sort(
            array_merge(
                $this->getStartDate($entries),
                $this->getStartDate($comments),
                $this->getStartDate($posts),
                $this->getStartDate($replies)
            )
        );

        if (empty($startDate) || !\array_key_exists('year', $startDate[0]) || !\array_key_exists('month', $startDate[0])) {
            return [
                'entries' => [],
                'comments' => [],
                'posts' => [],
                'replies' => [],
            ];
        }

        return [
            'entries' => $this->prepareContentOverall(
                $this->sort($entries),
                $startDate[0]['year'],
                $startDate[0]['month']
            ),
            'comments' => $this->prepareContentOverall(
                $this->sort($comments),
                $startDate[0]['year'],
                $startDate[0]['month']
            ),
            'posts' => $this->prepareContentOverall($this->sort($posts), $startDate[0]['year'], $startDate[0]['month']),
            'replies' => $this->prepareContentOverall(
                $this->sort($replies),
                $startDate[0]['year'],
                $startDate[0]['month']
            ),
        ];
    }
}
