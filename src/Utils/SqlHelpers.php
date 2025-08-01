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
            if (empty($whereClause)) {
                continue;
            }

            if ($i > 0) {
                $where .= ' AND ';
            }
            $where .= "($whereClause)";
            ++$i;
        }

        return $where;
    }

    /**
     * This method rewrites the parameter array and the native sql string to make use of array parameters
     * which are not supported by sql directly. Keep in mind that postgresql has a limit of 65k parameters
     * and each one of the array values counts as one parameter (because it only works that way).
     *
     * @return array{'sql': string, 'parameters': array}>
     */
    public static function rewriteArrayParameters(array $parameters, string $sql): array
    {
        $newParameters = [];
        $newSql = $sql;
        foreach ($parameters as $name => $value) {
            if (\is_array($value)) {
                $size = \sizeof($value);
                $newParameterNames = [];
                for ($i = 0; $i < $size; ++$i) {
                    $newParameters["$name$i"] = $value[$i];
                    $newParameterNames[] = ":$name$i";
                }
                $newSql = str_replace(":$name", join(',', $newParameterNames), $sql);
            } else {
                $newParameters[$name] = $value;
            }
        }

        return [
            'parameters' => $newParameters,
            'sql' => $newSql,
        ];
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
