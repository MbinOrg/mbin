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

        return $this->prepareContentReturn($entries, $comments, $posts, $replies);
    }

    #[ArrayShape([[
        'month' => 'string',
        'year' => 'string',
        'up' => 'int',
        'down' => 'int',
        'boost' => 'int',
    ]])]
    private function getMonthlyStats(string $table, string $relation = null): array
    {
        $votes = $this->getMonthlyVoteStats($table, $relation);
        $favourites = $this->getMonthlyFavouriteStats($table);
        $dateMap = [];
        for ($i = 0; $i < \count($votes); ++$i) {
            $key = $votes[$i]['year'].'-'.$votes[$i]['month'];
            $dateMap[$key] = $i;
            $votes[$i]['up'] = 0;
        }

        foreach ($favourites as $favourite) {
            $key = $favourite['year'].'-'.$favourite['month'];
            if (\array_key_exists($key, $dateMap)) {
                $i = $dateMap[$key];
                $votes[$i]['up'] = $favourite['up'];
            } else {
                $votes[] = [
                    'year' => $favourite['year'],
                    'month' => $favourite['month'],
                    'up' => $favourite['up'],
                    'boost' => 0,
                    'down' => 0,
                ];
            }
        }

        return array_map(fn ($val) => [
            'month' => date_parse($val['month'])['month'],
            'year' => (int) $val['year'],
            'up' => (int) $val['up'],
            'down' => (int) $val['down'],
            'boost' => (int) $val['boost'],
        ], $votes);
    }

    #[ArrayShape([[
        'month' => 'string',
        'year' => 'string',
        'boost' => 'int',
        'down' => 'int',
    ]])]
    private function getMonthlyVoteStats(string $table, string $relation): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? 'AND u.ap_id IS NULL' : '';
        $userWhere = $this->user ? ' AND e.user_id = :userId ' : '';
        $magazineJoin = $this->magazine ? 'INNER JOIN '.str_replace('_vote', '', $table).' AS parent ON '.$relation.' = parent.id AND parent.magazine_id = :magazineId' : '';
        $sql = "SELECT to_char(e.created_at,'Mon') as month, extract(year from e.created_at) as year,
            COUNT(case e.choice when 1 then 1 else null end) as boost, COUNT(case e.choice when -1 then 1 else null end) as down FROM $table e
            INNER JOIN public.user u ON u.id = e.user_id
            $magazineJoin
            WHERE u.is_deleted = false $onlyLocalWhere $userWhere GROUP BY 1,2";

        $stmt = $conn->prepare($sql);
        if ($this->user) {
            $stmt->bindValue('userId', $this->user->getId());
        } elseif ($this->magazine) {
            $stmt->bindValue('magazineId', $this->magazine->getId());
        }

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    #[ArrayShape([[
        'month' => 'string',
        'year' => 'string',
        'up' => 'int',
    ]])]
    private function getMonthlyFavouriteStats(string $table): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? 'AND u.ap_id IS NULL' : '';
        $userWhere = $this->user ? ' AND f.user_id = :userId ' : '';
        $magazineWhere = $this->magazine ? 'AND f.magazine_id = :magazineId ' : '';
        $idCol = str_replace('_vote', '', $table).'_id';
        $sql = "SELECT to_char(f.created_at,'Mon') as month, extract(year from f.created_at) as year, COUNT(f.id) as up FROM favourite f
            INNER JOIN public.user u ON u.id = f.user_id
            WHERE u.is_deleted = false AND f.$idCol IS NOT NULL $magazineWhere $onlyLocalWhere $userWhere GROUP BY 1,2";

        $stmt = $conn->prepare($sql);
        if ($this->user) {
            $stmt->bindValue('userId', $this->user->getId());
        } elseif ($this->magazine) {
            $stmt->bindValue('magazineId', $this->magazine->getId());
        }

        return $stmt->executeQuery()->fetchAllAssociative();
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
                    'boost' => 0,
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

    #[ArrayShape([[
        'day' => 'string',
        'up' => 'int',
        'down' => 'int',
        'boost' => 'int',
    ]])]
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
                'boost' => 0,
            ];
        }

        return $results;
    }

    #[ArrayShape([[
        'day' => 'string',
        'up' => 'int',
        'down' => 'int',
        'boost' => 'int',
    ]])]
    private function getDailyStats(string $table, string $relation): array
    {
        $results = $this->getDailyVoteStats($table, $relation);
        $dateMap = [];
        for ($i = 0; $i < \count($results); ++$i) {
            $dateMap[$results[$i]['day']] = $i;
            $results[$i]['up'] = 0;
        }
        $favourites = $this->getDailyFavouriteStats($table);

        foreach ($favourites as $favourite) {
            if (\array_key_exists($favourite['day'], $dateMap)) {
                $results[$dateMap[$favourite['day']]]['up'] = $favourite['up'];
            } else {
                $results[] = [
                    'day' => $favourite['day'],
                    'boost' => 0,
                    'down' => 0,
                    'up' => $favourite['up'],
                ];
            }
        }

        usort($results, fn ($a, $b): int => $a['day'] <=> $b['day']);

        return $results;
    }

    #[ArrayShape([[
        'day' => 'string',
        'down' => 'int',
        'boost' => 'int',
    ]])]
    private function getDailyVoteStats(string $table, string $relation): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? ' AND u.ap_id IS NULL ' : '';
        $userWhere = $this->user ? ' AND e.user_id = :userId ' : '';
        $magazineJoin = $this->magazine ? 'INNER JOIN '.str_replace('_vote', '', $table).' AS parent ON '.$relation.' = parent.id AND parent.magazine_id = :magazineId' : '';
        $sql = "SELECT date_trunc('day', e.created_at) as day, COUNT(case e.choice when 1 then 1 else null end) as boost, 
            COUNT(case e.choice when -1 then 1 else null end) as down FROM $table e
            INNER JOIN public.user u ON u.id = e.user_id
            $magazineJoin
            WHERE u.is_deleted = false AND e.created_at >= :startDate $userWhere $onlyLocalWhere
            GROUP BY 1";

        $stmt = $conn->prepare($sql);
        if ($this->user) {
            $stmt->bindValue('userId', $this->user->getId());
        }
        if ($this->magazine) {
            $stmt->bindValue('magazineId', $this->magazine->getId());
        }
        $stmt->bindValue('startDate', $this->start, 'datetime');
        $stmt = $stmt->executeQuery();

        return $stmt->fetchAllAssociative();
    }

    #[ArrayShape([[
        'day' => 'string',
        'up' => 'int',
    ]])]
    private function getDailyFavouriteStats(string $table): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $idCol = str_replace('_vote', '', $table).'_id';
        $magazineWhere = $this->magazine ? ' AND f.magazine_id = :magazineId' : '';
        $userWhere = $this->user ? ' AND f.user_id = :userId ' : '';
        $onlyLocalWhere = $this->onlyLocal ? ' AND u.ap_id IS NULL ' : '';
        $favSql = "SELECT date_trunc('day', f.created_at) as day, COUNT(f.id) as up FROM favourite f
            INNER JOIN public.user u ON f.user_id = u.id
            WHERE u.is_deleted = false AND f.created_at >= :startDate AND f.$idCol IS NOT NULL $onlyLocalWhere $magazineWhere $userWhere
            GROUP BY 1";
        $stmt = $conn->prepare($favSql);
        if ($this->user) {
            $stmt->bindValue('userId', $this->user->getId());
        }
        if ($this->magazine) {
            $stmt->bindValue('magazineId', $this->magazine->getId());
        }
        $stmt->bindValue('startDate', $this->start, 'datetime');
        $stmt = $stmt->executeQuery();

        return $stmt->fetchAllAssociative();
    }

    public function getStats(?Magazine $magazine, string $intervalStr, ?\DateTime $start, ?\DateTime $end, ?bool $onlyLocal): array
    {
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

        $results = [];

        foreach (['entry', 'entry_comment', 'post', 'post_comment'] as $table) {
            $results[$table] = $this->aggregateVoteStats($table, $magazine, $interval, $end);
            $datemap = [];
            for ($i = 0; $i < \count($results[$table]); ++$i) {
                $datemap[$results[$table][$i]['datetime']] = $i;
                $results[$table][$i]['up'] = 0;
            }

            $favourites = $this->aggregateFavouriteStats($table, $magazine, $interval, $end);
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

            usort($results[$table], fn ($a, $b): int => $a['datetime'] <=> $b['datetime']);
        }

        return $results;
    }

    private function aggregateVoteStats(string $table, ?Magazine $magazine, string $interval, \DateTime $end): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $relation = false === strstr($table, '_comment') ? $table.'_id' : 'comment_id';
        $voteTable = $table.'_vote';
        $magazineJoinCond = $magazine ? ' AND parent.magazine_id = ? ' : '';
        $onlyLocalWhere = $this->onlyLocal ? 'u.ap_id IS NULL ' : '';
        $sql = "SELECT date_trunc(?, e.created_at) as datetime, COUNT(case e.choice when 1 then 1 else null end) as boost, COUNT(case e.choice when -1 then 1 else null end) as down FROM $voteTable e 
                        INNER JOIN $table AS parent ON $relation = parent.id
                        INNER JOIN public.user u ON e.user_id = u.id $magazineJoinCond
                        WHERE u.is_deleted = false AND e.created_at BETWEEN ? AND ? $onlyLocalWhere GROUP BY 1 ORDER BY 1";

        $stmt = $conn->prepare($sql);
        $index = 1;
        $stmt->bindValue($index++, $interval);
        if ($magazine) {
            $stmt->bindValue($index++, $magazine->getId(), ParameterType::INTEGER);
        }
        $stmt->bindValue($index++, $this->start, 'datetime');
        $stmt->bindValue($index++, $end, 'datetime');

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    private function aggregateFavouriteStats(string $table, ?Magazine $magazine, string $interval, \DateTime $end): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $magazineWhere = $magazine ? ' AND e.magazine_id = ? ' : '';
        $onlyLocalWhere = $this->onlyLocal ? 'u.ap_id IS NULL ' : '';
        $idCol = $table.'_id';
        $sql = "SELECT date_trunc(?, e.created_at) as datetime, COUNT(e.id) as up FROM favourite e 
                INNER JOIN public.user u on e.user_id = u.id
                WHERE u.is_deleted = false AND e.created_at BETWEEN ? AND ? AND e.$idCol IS NOT NULL $magazineWhere $onlyLocalWhere GROUP BY 1 ORDER BY 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $interval);
        $stmt->bindValue(2, $this->start, 'datetime');
        $stmt->bindValue(3, $end, 'datetime');
        if ($magazine) {
            $stmt->bindValue(4, $magazine->getId(), ParameterType::INTEGER);
        }

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    private function aggregateTotalStats(?Magazine $magazine): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $results = [];

        foreach (['entry', 'entry_comment', 'post', 'post_comment'] as $table) {
            $relation = false === strstr($table, '_comment') ? $table.'_id' : 'comment_id';
            $voteTable = $table.'_vote';
            $magazineJoinCond = $magazine ? ' AND parent.magazine_id = ?' : '';
            $onlyLocalWhere = $this->onlyLocal ? ' u.ap_id IS NULL ' : '';
            $sql = "SELECT COUNT(case e.choice when 1 then 1 else null end) as boost, COUNT(case e.choice when -1 then 1 else null end) as down FROM $voteTable e
                INNER JOIN public.user u ON e.user_id = u.id
                INNER JOIN $table AS parent ON $relation = parent.id $magazineJoinCond
                WHERE u.is_deleted = false $onlyLocalWhere";

            $stmt = $conn->prepare($sql);
            if ($magazine) {
                $stmt->bindValue(1, $magazine->getId(), ParameterType::INTEGER);
            }

            $results[$table] = $stmt->executeQuery()->fetchAllAssociative();

            $magazineWhere = $magazine ? ' AND e.magazine_id = ?' : '';
            $idCol = $table.'_id';
            $sql = "SELECT COUNT(e.id) as up FROM favourite e 
                INNER JOIN public.user u on u.id = e.user_id
                WHERE u.is_deleted = false $magazineWhere $onlyLocalWhere AND e.$idCol IS NOT NULL";

            $stmt = $conn->prepare($sql);
            if ($magazine) {
                $stmt->bindValue(1, $magazine->getId(), ParameterType::INTEGER);
            }

            $favourites = $stmt->executeQuery()->fetchAllAssociative();

            if (0 < \count($results[$table]) && 0 < \count($favourites)) {
                $results[$table][0]['up'] = $favourites[0]['up'];
            } elseif (0 < \count($favourites)) {
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

            usort($results[$table], fn ($a, $b): int => $a['datetime'] <=> $b['datetime']);
        }

        return $results;
    }
}
