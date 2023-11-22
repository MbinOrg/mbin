<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;

class VoteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function count(\DateTimeImmutable $date = null, bool $withFederated = null): int
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
        (SELECT id, 'entry' AS type FROM entry_vote {$this->where($date, $withFederated)}) 
        UNION ALL
        (SELECT id, 'entry_comment' AS type FROM entry_comment_vote {$this->where($date, $withFederated)})
        UNION ALL
        (SELECT id, 'post' AS type FROM post_vote {$this->where($date, $withFederated)})
        UNION ALL
        (SELECT id, 'post_comment' AS type FROM post_comment_vote {$this->where($date, $withFederated)})
        ";

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery();

        return $stmt->rowCount();
    }

    private function where(\DateTimeImmutable $date = null, bool $withFederated = null): string
    {
        $dateWhere = $date ? "created_at > '{$date->format('Y-m-d H:i:s')}'" : '';
        $federationWhere = !$withFederated ? ' AND EXISTS (SELECT * FROM public.user WHERE public.user.ap_id IS NULL and public.user.id=user_id)' : '';

        return $dateWhere ? "WHERE $dateWhere$federationWhere" : '';
    }
}
