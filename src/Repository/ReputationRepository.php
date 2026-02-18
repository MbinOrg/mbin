<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use App\Entity\User;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Transformation\ContentPopulationTransformer;
use App\Service\SettingsManager;
use App\Utils\DownvotesMode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;

class ReputationRepository extends ServiceEntityRepository
{
    public const TYPE_ENTRY = 'threads';
    public const TYPE_ENTRY_COMMENT = 'comments';
    public const TYPE_POST = 'posts';
    public const TYPE_POST_COMMENT = 'replies';

    public const PER_PAGE = 48;

    public function __construct(
        ManagerRegistry $registry,
        private readonly SettingsManager $settingsManager,
        private readonly ContentPopulationTransformer $contentPopulationTransformer,
        private readonly CacheInterface $cache,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($registry, Site::class);
    }

    public function getUserReputation(User $user, string $className, int $page = 1): PagerfantaInterface
    {
        $table = $this->getEntityManager()->getClassMetadata($className)->getTableName();
        $voteTable = $table.'_vote';
        $idColumn = $table.'_id';

        $sql = "SELECT date_trunc('day', created_at) as day, sum(choice) as points FROM (
            SELECT v.created_at, v.choice FROM $voteTable v WHERE v.author_id = :userId AND v.choice = -1 --downvotes
            UNION ALL
            SELECT v.created_at, 2 as choice FROM $voteTable v WHERE v.author_id = :userId AND v.choice = 1 --boosts -> 2x
            UNION ALL
            SELECT f.created_at, 1 as choice FROM favourite f INNER JOIN $table s ON f.$idColumn = s.id WHERE s.user_id = :userId --upvotes -> 1x
        ) as interactions GROUP BY day ORDER BY day DESC";

        $adapter = new NativeQueryAdapter($this->entityManager->getConnection(), $sql, ['userId' => $user->getId()], cache: $this->cache);
        $pagerfanta = new Pagerfanta($adapter);

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function getUserReputationTotal(User $user): int
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        if (DownvotesMode::Disabled === $this->settingsManager->getDownvotesMode()) {
            $sql = 'SELECT
                COALESCE((SELECT SUM((up_votes * 2) + favourite_count) FROM entry WHERE user_id = :user), 0) +
                COALESCE((SELECT SUM((up_votes * 2) + favourite_count) FROM entry_comment WHERE user_id = :user), 0) +
                COALESCE((SELECT SUM((up_votes * 2) + favourite_count) FROM post WHERE user_id = :user), 0) +
                COALESCE((SELECT SUM((up_votes * 2) + favourite_count) FROM post_comment WHERE user_id = :user), 0) as total';
        } else {
            $sql = 'SELECT
                COALESCE((SELECT SUM((up_votes * 2) - down_votes + favourite_count) FROM entry WHERE user_id = :user), 0) +
                COALESCE((SELECT SUM((up_votes * 2) - down_votes + favourite_count) FROM entry_comment WHERE user_id = :user), 0) +
                COALESCE((SELECT SUM((up_votes * 2) - down_votes + favourite_count) FROM post WHERE user_id = :user), 0) +
                COALESCE((SELECT SUM((up_votes * 2) - down_votes + favourite_count) FROM post_comment WHERE user_id = :user), 0) as total';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('user', $user->getId());
        $stmt = $stmt->executeQuery();

        return $stmt->fetchAllAssociative()[0]['total'] ?? 0;
    }

    /**
     * @return float[] the percentage of upvotes a user gives (0-100) indexed by the userId
     *
     * @throws Exception
     */
    public function getUserAttitudes(int ...$userIds): array
    {
        if (DownvotesMode::Disabled === $this->settingsManager->getDownvotesMode()) {
            return array_map(fn () => 0, $userIds);
        }
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT sum(up_votes) as up_votes, sum(down_votes)as down_votes, user_id FROM (
                (SELECT COUNT(*) as up_votes, 0 as down_votes, f.user_id as user_id FROM favourite f WHERE f.user_id IN (?) GROUP BY user_id)
                UNION ALL
                (SELECT COUNT(*) as up_votes, 0 as down_votes, v.user_id as user_id FROM entry_vote v WHERE v.user_id IN (?) AND v.choice = 1 GROUP BY user_id)
                UNION ALL
                (SELECT COUNT(*) as up_votes, 0 as down_votes, v.user_id as user_id FROM entry_comment_vote v WHERE v.user_id IN (?) AND v.choice = 1 GROUP BY user_id)
                UNION ALL
                (SELECT COUNT(*) as up_votes, 0 as down_votes, v.user_id as user_id FROM post_vote v WHERE v.user_id IN (?) AND v.choice = 1 GROUP BY user_id)
                UNION ALL
                (SELECT COUNT(*) as up_votes, 0 as down_votes, v.user_id as user_id FROM post_comment_vote v WHERE v.user_id IN (?) AND v.choice = 1 GROUP BY user_id)
                UNION ALL
                (SELECT 0 as up_votes, COUNT(*) as down_votes, v.user_id as user_id FROM entry_vote v WHERE v.user_id IN (?) AND v.choice = -1 GROUP BY user_id)
                UNION ALL
                (SELECT 0 as up_votes, COUNT(*) as down_votes, v.user_id as user_id FROM entry_comment_vote v WHERE v.user_id IN (?) AND v.choice = -1 GROUP BY user_id)
                UNION ALL
                (SELECT 0 as up_votes, COUNT(*) as down_votes, v.user_id as user_id FROM post_vote v WHERE v.user_id IN (?) AND v.choice = -1 GROUP BY user_id)
                UNION ALL
                (SELECT 0 as up_votes, COUNT(*) as down_votes, v.user_id as user_id FROM post_comment_vote v WHERE v.user_id IN (?) AND v.choice = -1 GROUP BY user_id)
            ) as votes GROUP BY user_id
        ';

        // array parameter types are ass in SQL, so this is the nicest way to bind the userIds to this query
        $rows = $conn->executeQuery(
            $sql,
            [$userIds, $userIds, $userIds, $userIds, $userIds, $userIds, $userIds, $userIds, $userIds],
            [ArrayParameterType::INTEGER, ArrayParameterType::INTEGER, ArrayParameterType::INTEGER, ArrayParameterType::INTEGER, ArrayParameterType::INTEGER, ArrayParameterType::INTEGER, ArrayParameterType::INTEGER, ArrayParameterType::INTEGER, ArrayParameterType::INTEGER]
        )
            ->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            $upVotes = $row['up_votes'] ?? 0;
            $downVotes = $row['down_votes'] ?? 0;
            $votes = $upVotes + $downVotes;
            if (0 === $votes) {
                $result[$row['user_id']] = -1;
                continue;
            }
            $result[$row['user_id']] = 100 / $votes * $upVotes;
        }

        return $result;
    }
}
