<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Magazine;
use App\Entity\User;
use Doctrine\DBAL\ParameterType;
use JetBrains\PhpStorm\ArrayShape;

class StatsVotesRepository extends StatsRepository
{
    #[ArrayShape(['entries' => 'array', 'comments' => 'array', 'posts' => 'array', 'replies' => 'array'])]
    public function getOverallStats(
        User $user = null,
        Magazine $magazine = null,
        bool $onlyLocal = null
    ): array {
        $this->user = $user;
        $this->magazine = $magazine;
        $this->onlyLocal = $onlyLocal;

        $entries = $this->getMonthlyStats('entry_vote', 'entry_id');
        $comments = $this->getMonthlyStats('entry_comment_vote', 'comment_id');
        $posts = $this->getMonthlyStats('post_vote', 'post_id');
        $replies = $this->getMonthlyStats('post_comment_vote', 'comment_id');

        $startDate = $this->sort(
            array_merge(
                $this->getStartDate($entries),
                $this->getStartDate($comments),
                $this->getStartDate($posts),
                $this->getStartDate($replies)
            )
        );

        if (empty($startDate)) {
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

    private function getMonthlyStats(string $table, string $relation = null): array
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? ' WHERE EXISTS (SELECT * FROM public.user WHERE public.user.id=e.user_id AND public.user.ap_id IS NULL)' : '';
        if ($this->user) {
            $sql = "SELECT to_char(e.created_at,'Mon') as month, extract(year from e.created_at) as year,
                    COUNT(case e.choice when 1 then 1 else null end) as up, COUNT(case e.choice when -1 then 1 else null end) as down FROM ".$table.'
                    e WHERE e.user_id = :userId '.$onlyLocalWhere.' GROUP BY 1,2';
        } elseif ($this->magazine) {
            $sql = "SELECT to_char(e.created_at,'Mon') as month, extract(year from e.created_at) as year, 
                    COUNT(case e.choice when 1 then 1 else null end) as up, COUNT(case e.choice when -1 then 1 else null end) as down FROM ".$table.'
                    e INNER JOIN '.str_replace('_vote', '', $table).' AS parent ON '.$relation.' = parent.id AND
                    parent.magazine_id = :magazineId '.$onlyLocalWhere.' GROUP BY 1,2';
        } else {
            $sql = "SELECT to_char(e.created_at,'Mon') as month, extract(year from e.created_at) as year, 
                    COUNT(case e.choice when 1 then 1 else null end) as up, COUNT(case e.choice when -1 then 1 else null end) as down FROM ".$table.'
                    e '.$onlyLocalWhere.' GROUP BY 1,2';
        }

        $stmt = $conn->prepare($sql);
        if ($this->user) {
            $stmt->bindValue('userId', $this->user->getId());
        } elseif ($this->magazine) {
            $stmt->bindValue('magazineId', $this->magazine->getId());
        }
        $stmt = $stmt->executeQuery();

        return array_map(fn ($val) => [
            'month' => date_parse($val['month'])['month'],
            'year' => (int) $val['year'],
            'up' => (int) $val['up'],
            'down' => (int) $val['down'],
        ], $stmt->fetchAllAssociative());
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
                    'up' => 0,
                    'down' => 0,
                ];
            }
        }

        return $results;
    }

    #[ArrayShape(['entries' => 'array', 'comments' => 'array', 'posts' => 'array', 'replies' => 'array'])]
    public function getStatsByTime(\DateTime $start, User $user = null, Magazine $magazine = null, bool $onlyLocal = null): array
    {
        $this->start = $start;
        $this->user = $user;
        $this->magazine = $magazine;
        $this->onlyLocal = $onlyLocal;

        return [
            'entries' => $this->prepareContentDaily($this->getDailyStats('entry_vote', 'entry_id')),
            'comments' => $this->prepareContentDaily($this->getDailyStats('entry_comment_vote', 'comment_id')),
            'posts' => $this->prepareContentDaily($this->getDailyStats('post_vote', 'post_id')),
            'replies' => $this->prepareContentDaily($this->getDailyStats('post_comment_vote', 'comment_id')),
        ];
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
                'up' => 0,
                'down' => 0,
            ];
        }

        return $results;
    }

    private function getDailyStats(string $table, string $relation = null): array
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? 'AND EXISTS (SELECT * FROM public.user WHERE public.user.id=e.user_id AND public.user.ap_id IS NULL) ' : '';
        if ($this->user) {
            $sql = "SELECT  date_trunc('day', e.created_at) as day, COUNT(case e.choice when 1 then 1 else null end) as up, 
                    COUNT(case e.choice when -1 then 1 else null end) as down FROM ".$table.' e
                    WHERE e.created_at >= :startDate AND e.user_id = :userId
                    '.$onlyLocalWhere.'
                    GROUP BY 1';
        } elseif ($this->magazine) {
            $sql = "SELECT  date_trunc('day', e.created_at) as day, COUNT(case e.choice when 1 then 1 else null end) as up, 
                    COUNT(case e.choice when -1 then 1 else null end) as down FROM ".$table.' e
                    INNER JOIN '.str_replace('_vote', '', $table).' AS parent
                    ON '.$relation.' = parent.id AND parent.magazine_id = :magazineId
                    WHERE e.created_at >= :startDate
                    '.$onlyLocalWhere.'
                    GROUP BY 1';
        } else {
            $sql = "SELECT  date_trunc('day', e.created_at) as day, COUNT(case e.choice when 1 then 1 else null end) as up,
                    COUNT(case e.choice when -1 then 1 else null end) as down FROM ".$table.' e
                    WHERE e.created_at >= :startDate
                    '.$onlyLocalWhere.'
                    GROUP BY 1';
        }

        $stmt = $conn->prepare($sql);
        if ($this->user) {
            $stmt->bindValue('userId', $this->user->getId());
        } elseif ($this->magazine) {
            $stmt->bindValue('magazineId', $this->magazine->getId());
        }
        $stmt->bindValue('startDate', $this->start->format('Y-m-d H:i:s'));
        $stmt = $stmt->executeQuery();

        $results = $stmt->fetchAllAssociative();

        usort($results, fn ($a, $b): int => $a['day'] <=> $b['day']);

        return $results;
    }

    public function getStats(
        ?Magazine $magazine,
        string $intervalStr,
        ?\DateTime $start,
        ?\DateTime $end,
        ?bool $onlyLocal
    ): array {
        $this->onlyLocal = $onlyLocal;
        $interval = $intervalStr ?? 'month';
        switch ($interval) {
            case 'all':
                return $this->aggregateTotalStats($magazine);
            case 'year':
            case 'month':
            case 'day':
            case 'hour':
                break;
            default:
                throw new \LogicException('Invalid interval provided');
        }

        $this->start = $start ?? new \DateTime('-1 '.$interval);

        return $this->aggregateStats($magazine, $interval, $end);
    }

    private function aggregateStats(?Magazine $magazine, string $interval, ?\DateTime $end): array
    {
        if (null === $end) {
            $end = new \DateTime();
        }

        if ($end < $this->start) {
            throw new \LogicException('End date must be after start date!');
        }

        $conn = $this->getEntityManager()->getConnection();

        $results = [];

        foreach (['entry', 'entry_comment', 'post', 'post_comment'] as $table) {
            $relation = false === strstr($table, '_comment') ? $table.'_id' : 'comment_id';

            // Common SQL for both queries
            $commonSql = 'SELECT date_trunc(:interval, e.created_at) as datetime ';

            // First Query
            $sql1 = $commonSql.', COUNT(case e.choice when 1 then 1 else null end) as boost, COUNT(case e.choice when -1 then 1 else null end) as down 
                FROM '.$table.'_vote e 
                INNER JOIN '.$table.' AS parent ON '.$relation.' = parent.id
                WHERE e.created_at BETWEEN :start AND :end';

            // Add magazine condition
            if ($magazine) {
                $sql1 .= ' AND parent.magazine_id = :magazineId';
            }

            // Add local condition
            if ($this->onlyLocal) {
                $sql1 .= ' AND EXISTS (SELECT * FROM public.user WHERE public.user.id=e.user_id AND public.user.ap_id IS NULL)';
            }

            $sql1 .= ' GROUP BY 1 ORDER BY 1';

            // Execute the first query
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bindValue(':interval', $interval);
            $stmt1->bindValue(':start', $this->start, 'datetime');
            $stmt1->bindValue(':end', $end, 'datetime');
            if ($magazine) {
                $stmt1->bindValue(':magazineId', $magazine->getId(), ParameterType::INTEGER);
            }

            $results[$table] = $stmt1->executeQuery()->fetchAllAssociative();

            // Second Query
            $sql2 = $commonSql.', COUNT(e.id) as up 
                FROM favourite e 
                WHERE e.created_at BETWEEN :start AND :end';

            // Add magazine condition
            if ($magazine) {
                $sql2 .= ' AND e.magazine_id = :magazineId';
            }

            // Add local condition
            if ($this->onlyLocal) {
                $sql2 .= ' AND EXISTS (SELECT * FROM public.user WHERE public.user.id=e.user_id AND public.user.ap_id IS NULL)';
            }

            $sql2 .= ' AND e.'.$table.'_id IS NOT NULL GROUP BY 1 ORDER BY 1';

            // Execute the second query
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindValue(':interval', $interval);
            $stmt2->bindValue(':start', $this->start, 'datetime');
            $stmt2->bindValue(':end', $end, 'datetime');
            if ($magazine) {
                $stmt2->bindValue(':magazineId', $magazine->getId(), ParameterType::INTEGER);
            }

            $favourites = $stmt2->executeQuery()->fetchAllAssociative();

            // Process the results without the helper function
            $datemap = [];

            foreach ($results[$table] as $i => $result) {
                $datemap[$result['datetime']] = $i;
                $results[$table][$i]['up'] = 0;
            }

            foreach ($favourites as $favourite) {
                if (\array_key_exists($favourite['datetime'], $datemap)) {
                    $results[$table][$datemap[$favourite['datetime']]]['up'] = $favourite['up'];
                } else {
                    $results[$table][] = [
                        'datetime' => $favourite['datetime'],
                        'boost' => 0,
                        'down' => 0,
                        'up' => $favourite['up'],
                    ];
                }
            }

            // Sort the results
            usort($results[$table], fn ($a, $b) => $a['datetime'] <=> $b['datetime']);
        }

        return $results;
    }

    private function aggregateTotalStats(?Magazine $magazine): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $results = [];

        foreach (['entry', 'entry_comment', 'post', 'post_comment'] as $table) {
            $relation = false === strstr($table, '_comment') ? $table.'_id' : 'comment_id';

            // First Query
            $sql1 = 'SELECT COUNT(case e.choice when 1 then 1 else null end) as boost, COUNT(case e.choice when -1 then 1 else null end) as down 
                FROM '.$table.'_vote e 
                INNER JOIN '.$table.' AS parent ON '.$relation.' = parent.id';

            // Add magazine condition
            if ($magazine) {
                $sql1 .= ' AND parent.magazine_id = ?';
            }

            // Add local condition
            if ($this->onlyLocal) {
                $sql1 .= ' AND EXISTS (SELECT * FROM public.user WHERE public.user.id=e.user_id AND public.user.ap_id IS NULL)';
            }

            $stmt1 = $conn->prepare($sql1);
            if ($magazine) {
                $stmt1->bindValue(1, $magazine->getId(), ParameterType::INTEGER);
            }

            $results[$table] = $stmt1->executeQuery()->fetchAllAssociative();

            // Second Query
            $sql2 = 'SELECT COUNT(e.id) as up FROM favourite e 
                WHERE e.'.$table.'_id IS NOT NULL';

            // Add magazine condition
            if ($magazine) {
                $sql2 .= ' AND e.magazine_id = ?';
            }

            // Add local condition
            if ($this->onlyLocal) {
                $sql2 .= ' AND EXISTS (SELECT * FROM public.user WHERE public.user.id=e.user_id AND public.user.ap_id IS NULL)';
            }

            $stmt2 = $conn->prepare($sql2);
            if ($magazine) {
                $stmt2->bindValue(1, $magazine->getId(), ParameterType::INTEGER);
            }

            $favourites = $stmt2->executeQuery()->fetchAllAssociative();

            if (\count($results[$table]) > 0 && \count($favourites) > 0) {
                $results[$table][0]['up'] = $favourites[0]['up'];
            } elseif (\count($favourites) > 0) {
                $results[$table][] = [
                    'boost' => 0,
                    'down' => 0,
                    'up' => $favourites[0]['up'],
                ];
            } else {
                $results[$table][] = [
                    'boost' => 0,
                    'down' => 0,
                    'up' => 0,
                ];
            }

            usort($results[$table], fn ($a, $b) => $a['datetime'] <=> $b['datetime']);
        }

        return $results;
    }
}
