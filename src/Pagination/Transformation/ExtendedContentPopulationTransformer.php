<?php
declare(strict_types=1);

namespace App\Pagination\Transformation;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Repository\Criteria;
use App\Repository\UserRepository;
use App\Utils\SqlHelpers;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

readonly class ExtendedContentPopulationTransformer extends ContentPopulationTransformer
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private Criteria $criteria,
        private ?User $loggedInUser,
    ) {
        parent::__construct($entityManager);
    }

    public function transform(iterable $input): iterable
    {
        $items = parent::transform($input);

        \assert(\is_array($input));
        \assert(\is_array($items));
        $this->extendItems($input, $items);

        return $items;
    }

    /**
     * @param array<Entry|EntryComment|Post|PostComment> $items
     */
    private function extendItems(array $rows, array $items): void
    {
        \assert(\count($rows) === \count($items));

        $hasUser = null !== $this->loggedInUser;
        foreach ($items as $i => $item) {
            $row = $rows[$i];
            \assert($row['id'] === $item->getId());

            if ($hasUser && isset($row['was_boosted']) && true === $row['was_boosted']) {
                $this->extendItemBoostList($item);
            }
        }
    }

    private function extendItemBoostList(Entry|EntryComment|Post|PostComment $item): void
    {
        switch (\get_class($item)) {
            case Entry::class:
                $vType = 'entry';
                $fkType = 'entry';
                break;
            case Post::class:
                $vType = 'post';
                $fkType = 'post';
                break;
            case EntryComment::class:
                $vType = 'entry_comment';
                $fkType = 'comment';
                break;
            case PostComment::class:
                $vType = 'post_comment';
                $fkType = 'comment';
                break;
            default:
                throw new \LogicException('unreachable');
        }

        if (null === $this->criteria->cachedUserFollows) {
            $sql = 'SELECT v.user_id, v.created_at FROM user_follow uf RIGHT OUTER JOIN %v_type%_vote v ON uf.following_id = v.user_id WHERE v.%fk_type%_id = :itemId AND (uf.follower_id = :loggedInUser OR v.user_id = :loggedInUser) AND v.choice = 1';
            $sql = str_replace('%v_type%', $vType, str_replace('%fk_type%', $fkType, $sql));

            $boostsQuery = $this->entityManager->getConnection()->prepare($sql);
            $boostsQuery->bindValue('itemId', $item->getId(), ParameterType::INTEGER);
            $boostsQuery->bindValue('loggedInUser', $this->loggedInUser->getId(), ParameterType::INTEGER);
        } else {
            $sql = 'SELECT v.user_id, v.created_at FROM %v_type%_vote v WHERE :itemId = v.%fk_type%_id AND (v.user_id IN (:cachedUserFollows) OR v.user_id = :loggedInUser) AND v.choice = 1';
            $sql = str_replace('%v_type%', $vType, str_replace('%fk_type%', $fkType, $sql));
            $parameters = [
                'itemId' => $item->getId(),
                'loggedInUser' => $this->loggedInUser->getId(),
                'cachedUserFollows' => $this->criteria->cachedUserFollows,
            ];
            $rewritten = SqlHelpers::rewriteArrayParameters($parameters, $sql);

            $boostsQuery = $this->entityManager->getConnection()->prepare($rewritten['sql']);
            foreach ($rewritten['parameters'] as $key => $value) {
                $boostsQuery->bindValue($key, $value, SqlHelpers::getSqlType($value));
            }
        }

        $boostInfo = $boostsQuery->executeQuery()->fetchAllAssociative();
        $boostUsers = $this->userRepository->findBy(['id' => array_map(fn ($row) => $row['user_id'], $boostInfo)]);

        $boostExtension = [];
        foreach ($boostUsers as $boostUser) {
            $boostTime = array_find($boostInfo, fn ($row) => $row['user_id'] === $boostUser->getId())['created_at'];
            $boostExtension[] = ['user' => $boostUser, 'time' => new \DateTimeImmutable($boostTime)];
        }
        usort($boostExtension, fn ($a, $b) => $a['time'] <=> $b['time']);

        $item->extendedContentProperties['boostUsers'] = $boostExtension;
    }
}
