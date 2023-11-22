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
        $entriesByDay = [];

        // Organize entries by day for efficient lookup
        foreach ($entries as $entry) {
            $dayKey = (new \DateTime($entry['day']))->format('Y-m-d');
            $entriesByDay[$dayKey] = $entry;
        }

        foreach ($period as $d) {
            $dayKey = $d->format('Y-m-d');

            if (isset($entriesByDay[$dayKey])) {
                $existed = $entriesByDay[$dayKey];
                $existed['day'] = new \DateTime($existed['day']);
            } else {
                $existed = ['day' => $d, 'count' => 0];
            }

            $results[] = $existed;
        }

        return $results;
    }

    protected function prepareContentOverall(array $entries, int $startYear, int $startMonth): array
    {
        $currentMonth = (int) (new \DateTime('now'))->format('n');
        $currentYear = (int) (new \DateTime('now'))->format('Y');

        $results = [];
        for ($y = $startYear; $y <= $currentYear; ++$y) {
            for ($m = max($startMonth, 1); $m <= min($currentMonth, 12); ++$m) {
                $existed = array_filter($entries, fn ($entry) => $entry['month'] === $m && (int) $entry['year'] === $y);

                $resultEntry = !empty($existed) ? current($existed) : ['month' => $m, 'year' => $y, 'count' => 0];
                $results[] = $resultEntry;
            }
        }

        return $results;
    }

    protected function getStartDate(array $values): array
    {
        return array_map(fn ($val) => ['year' => $val['year'], 'month' => $val['month']], $values);
    }
}
