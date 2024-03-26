<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Magazine;
use App\Entity\User;
use Doctrine\DBAL\ParameterType;

class StatsViewsRepository extends StatsRepository
{
    public function getOverallStats(
        User $user = null,
        Magazine $magazine = null,
        bool $onlyLocal = null
    ): array {
        $this->user = $user;
        $this->magazine = $magazine;
        $this->onlyLocal = $onlyLocal;

        return $this->sort($this->getMonthlyStats());
    }

    private function getMonthlyStats(): array
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? ' AND e.ap_id IS NULL ' : '';
        $magazineWhere = $this->magazine ? ' AND e.magazine_id = :magazineId ' : '';
        $userWhere = $this->user ? ' AND e.user_id = :userId ' : '';
        $sql = "SELECT to_char(e.created_at, 'Mon') as month, extract(year from e.created_at) as year, SUM(e.views) as count 
                FROM entry e INNER JOIN public.user u ON e.user_id = u.id 
                WHERE u.is_deleted = false $onlyLocalWhere $magazineWhere $userWhere GROUP BY 1,2";

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
            'count' => (int) $val['count'],
        ], $stmt->fetchAllAssociative());
    }

    public function getStatsByTime(\DateTime $start, ?User $user, ?Magazine $magazine, ?bool $onlyLocal): array
    {
        $this->start = $start;
        $this->user = $user;
        $this->magazine = $magazine;
        $this->onlyLocal = $onlyLocal;

        return $this->prepareContentDaily($this->getDailyStats());
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

        $conn = $this->getEntityManager()->getConnection();

        $magazineWhere = $magazine ? ' AND e.magazine_id = ? ' : '';
        $onlyLocalWhere = $this->onlyLocal ? ' AND e.ap_id IS NULL ' : '';

        $sql = "SELECT date_trunc(?, e.created_at) as datetime, SUM(e.views) as count FROM entry e 
                    INNER JOIN public.user u on u.id = e.user_id
                    WHERE u.is_deleted = false AND e.created_at BETWEEN ? AND ? $magazineWhere $onlyLocalWhere GROUP BY 1 ORDER BY 1";

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

        $magazineWhere = $magazine ? ' AND e.magazine_id = ? ' : '';
        $onlyLocalWhere = $this->onlyLocal ? ' AND e.ap_id IS NULL ' : '';

        $sql = "SELECT SUM(e.views) as count FROM entry e INNER JOIN public.user u on e.user_id = u.id WHERE u.is_deleted = false $magazineWhere $onlyLocalWhere";

        $stmt = $conn->prepare($sql);
        if ($magazine) {
            $stmt->bindValue('magazineId', $magazine->getId());
        }

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    private function getDailyStats(): array
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? ' AND e.ap_id IS NULL ' : '';
        $userWhere = $this->user ? ' AND e.user_id = :userId ' : '';
        $magazineWhere = $this->magazine ? ' AND e.magazine_id = :magId ' : '';

        $sql = "SELECT date_trunc('day', e.created_at) as day, SUM(e.views) as count 
            FROM entry e INNER JOIN public.user u on e.user_id = u.id 
            WHERE u.is_deleted = false AND e.created_at >= :date $userWhere $magazineWhere $onlyLocalWhere
            GROUP BY 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':date', $this->start, 'datetime');

        if ($this->user) {
            $stmt->bindValue(':userId', $this->user->getId());
        }

        if ($this->magazine) {
            $stmt->bindValue(':magId', $this->magazine->getId());
        }

        $stmt = $stmt->executeQuery();

        $results = $stmt->fetchAllAssociative();

        usort($results, fn ($a, $b): int => $a['day'] <=> $b['day']);

        return $results;
    }
}
