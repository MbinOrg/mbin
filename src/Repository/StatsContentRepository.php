<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Magazine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;
use JetBrains\PhpStorm\ArrayShape;

class StatsContentRepository extends StatsRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($registry);
    }

    #[ArrayShape(['entries' => 'array', 'comments' => 'array', 'posts' => 'array', 'replies' => 'array'])]
    public function getOverallStats(
        ?User $user = null,
        ?Magazine $magazine = null,
        ?bool $onlyLocal = null,
    ): array {
        $this->user = $user;
        $this->magazine = $magazine;
        $this->onlyLocal = $onlyLocal;

        $entries = $this->getMonthlyStats('entry');
        $comments = $this->getMonthlyStats('entry_comment');
        $posts = $this->getMonthlyStats('post');
        $replies = $this->getMonthlyStats('post_comment');

        return $this->prepareContentReturn($entries, $comments, $posts, $replies);
    }

    private function getMonthlyStats(string $table): array
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? ' AND e.ap_id IS NULL' : '';
        $userWhere = $this->user ? ' AND e.user_id = :userId ' : '';
        $magazineWhere = $this->magazine ? ' AND e.magazine_id = :magazineId ' : '';
        $sql = "SELECT to_char(e.created_at,'Mon') as month, extract(year from e.created_at) as year, COUNT(e.id) as count FROM $table e
            INNER JOIN public.user u ON u.id = user_id
            WHERE u.is_deleted = false $onlyLocalWhere $userWhere $magazineWhere GROUP BY 1,2";

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

    #[ArrayShape(['entries' => 'array', 'comments' => 'array', 'posts' => 'array', 'replies' => 'array'])]
    public function getStatsByTime(\DateTime $start, ?User $user = null, ?Magazine $magazine = null, ?bool $onlyLocal = null): array
    {
        $this->start = $start;
        $this->user = $user;
        $this->magazine = $magazine;
        $this->onlyLocal = $onlyLocal;

        return [
            'entries' => $this->prepareContentDaily($this->getDailyStats('entry')),
            'comments' => $this->prepareContentDaily($this->getDailyStats('entry_comment')),
            'posts' => $this->prepareContentDaily($this->getDailyStats('post')),
            'replies' => $this->prepareContentDaily($this->getDailyStats('post_comment')),
        ];
    }

    private function getDailyStats(string $table): array
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $onlyLocalWhere = $this->onlyLocal ? ' AND e.ap_id IS NULL' : '';
        $userWhere = $this->user ? ' AND e.user_id = :userId ' : '';
        $magazineWhere = $this->magazine ? ' AND e.magazine_id = :magazineId ' : '';
        $sql = "SELECT date_trunc('day', e.created_at) as day, COUNT(e.id) as count FROM $table e
            INNER JOIN public.user u ON e.user_id = u.id
            WHERE u.is_deleted = false AND e.created_at >= :startDate $userWhere $magazineWhere $onlyLocalWhere GROUP BY 1";

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

    public function getStats(?Magazine $magazine, string $interval, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end, ?bool $onlyLocal): array
    {
        switch ($interval) {
            case 'all':
            case 'year':
            case 'month':
            case 'day':
            case 'hour':
                break;
            default:
                throw new \LogicException('Invalid interval provided');
        }
        if (null !== $start && null === $end) {
            $end = $start->modify('+1 '.$interval);
        } elseif (null === $start && null !== $end) {
            $start = $end->modify('-1 '.$interval);
        }

        return [
            'entry' => $this->aggregateStats('entry', $start, $end, true !== $onlyLocal, $magazine),
            'entry_comment' => $this->aggregateStats('entry_comment', $start, $end, true !== $onlyLocal, $magazine),
            'post' => $this->aggregateStats('post', $start, $end, true !== $onlyLocal, $magazine),
            'post_comment' => $this->aggregateStats('post_comment', $start, $end, true !== $onlyLocal, $magazine),
        ];
    }

    public function aggregateStats(string $tableName, ?\DateTimeImmutable $sinceDate, ?\DateTimeImmutable $tilDate, bool $federated, ?Magazine $magazine): int
    {
        $tableName = match ($tableName) {
            'entry' => 'entry',
            'entry_comment' => 'entry_comment',
            'post' => 'post',
            'post_comment' => 'post_comment',
            default => throw new \InvalidArgumentException("$tableName is not a valid countable"),
        };

        $federatedCond = false === $federated ? ' AND e.ap_id IS NULL ' : '';
        $magazineCond = $magazine ? 'AND e.magazine_id = :magId' : '';
        $sinceDateCond = $sinceDate ? 'AND e.created_at > :date' : '';
        $tilDateCond = $tilDate ? 'AND e.created_at < :untilDate' : '';

        $sql = "SELECT COUNT(e.id) as count FROM $tableName e INNER JOIN public.user u ON e.user_id = u.id WHERE u.is_deleted = false $sinceDateCond $tilDateCond $federatedCond $magazineCond";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('count', 0);
        $query = $this->entityManager->createNativeQuery($sql, $rsm);

        if (null !== $sinceDate) {
            $query->setParameter(':date', $sinceDate);
        }

        if (null !== $tilDate) {
            $query->setParameter(':untilDate', $tilDate);
        }

        if (null !== $magazine) {
            $query->setParameter(':magId', $magazine->getId());
        }
        $res = $query->getScalarResult();

        if (0 === \sizeof($res) || 0 === \sizeof($res[0])) {
            return 0;
        }

        return $res[0][0];
    }

    public function countLocalPosts(): int
    {
        $entries = $this->aggregateStats('entry', null, null, false, null);
        $posts = $this->aggregateStats('post', null, null, false, null);

        return $entries + $posts;
    }

    public function countLocalComments(): int
    {
        $entryComments = $this->aggregateStats('entry_comment', null, null, false, null);
        $postComments = $this->aggregateStats('post_comment', null, null, false, null);

        return $entryComments + $postComments;
    }

    public function countUsers(?\DateTime $startDate = null): int
    {
        $users = $this->_em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.apId IS NULL')
            ->andWhere('u.isDeleted = false')
        ;

        if ($startDate) {
            $users->andWhere('u.lastActive >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        return $users->getQuery()
            ->getSingleScalarResult();
    }
}
