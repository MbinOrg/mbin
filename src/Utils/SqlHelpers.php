<?php

declare(strict_types=1);

namespace App\Utils;

use App\Entity\MagazineBlock;
use App\Entity\User;
use App\Entity\UserBlock;
use Doctrine\ORM\EntityManagerInterface;

class SqlHelpers
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function makeWhereString(array $whereClauses): string
    {
        if (empty($whereClauses)) {
            return '';
        }

        $where = 'WHERE ';
        $i = 0;
        foreach ($whereClauses as $whereClause) {
            if ($i > 0) {
                $where .= ' AND ';
            }
            $where .= $whereClause;
            ++$i;
        }

        return $where;
    }

    public function getBlockedMagazinesDql(User $user): string
    {
        return $this->entityManager->createQueryBuilder()
            ->select('bm')
            ->from(MagazineBlock::class, 'bm')
            ->where('bm.magazine = m')
            ->andWhere('bm.user = :user')
            ->setParameter('user', $user)
            ->getDQL();
    }

    public function getBlockedUsersDql(User $user): string
    {
        return $this->entityManager->createQueryBuilder()
            ->select('ub')
            ->from(UserBlock::class, 'ub')
            ->where('ub.blocker = :user')
            ->andWhere('ub.blocked = u')
            ->setParameter('user', $user)
            ->getDql();
    }
}
