<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;

readonly class VoteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function count(?\DateTimeImmutable $date = null, ?bool $withFederated = null): int
    {
        $count = 0;
        foreach (['entry_vote', 'entry_comment_vote', 'post_vote', 'post_comment_vote', 'favourite'] as $table) {
            $conn = $this->entityManager->getConnection();
            $sql = "SELECT COUNT(e.id) as cnt FROM $table e INNER JOIN public.user u ON user_id=u.id {$this->where($date, $withFederated)}";

            $stmt = $conn->prepare($sql);
            if (null !== $date) {
                $stmt->bindValue(':date', $date, 'datetime');
            }
            $stmt = $stmt->executeQuery();
            $count += $stmt->fetchAllAssociative()[0]['cnt'];
        }

        return $count;
    }

    private function where(?\DateTimeImmutable $date = null, ?bool $withFederated = null): string
    {
        $where = 'WHERE u.is_deleted = false';
        $dateWhere = $date ? ' AND e.created_at > :date' : '';
        $federationWhere = $withFederated ? '' : ' AND u.ap_id IS NULL';

        return $where.$dateWhere.$federationWhere;
    }
}
