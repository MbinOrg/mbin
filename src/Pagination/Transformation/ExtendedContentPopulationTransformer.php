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

        if ($hasUser) {
            $this->extendItemsBoostList($rows, $items);
        }
    }

    /**
     * @param array<Entry|EntryComment|Post|PostComment> $items
     */
    private function extendItemsBoostList(array $rows, array $items): void
    {
        $boostedEntries = [];
        $boostedPosts = [];
        $boostedEntryComments = [];
        $boostedPostComments = [];

        foreach ($items as $i => $item) {
            $row = $rows[$i];
            \assert($row['id'] === $item->getId());

            if (isset($row['was_boosted']) && true === $row['was_boosted']) {
                match (true) {
                    $item instanceof Entry => $boostedEntries[$item->getId()] = $item,
                    $item instanceof Post => $boostedPosts[$item->getId()] = $item,
                    $item instanceof EntryComment => $boostedEntryComments[$item->getId()] = $item,
                    $item instanceof PostComment => $boostedPostComments[$item->getId()] = $item,
                    default => throw new \LogicException('unreachable'),
                };
            }
        }

        $userCache = [];
        if (\count($boostedEntries) > 0) {
            $boostInfos = $this->queryBoostInfo(array_keys($boostedEntries), 'Entry', $userCache);
            foreach ($boostInfos as $id => $boosts) {
                $boostedEntries[$id]->extendedContentProperties['boostUsers'] = $boosts;
            }
        }
        if (\count($boostedPosts) > 0) {
            $boostInfos = $this->queryBoostInfo(array_keys($boostedPosts), 'Post', $userCache);
            foreach ($boostInfos as $id => $boosts) {
                $boostedPosts[$id]->extendedContentProperties['boostUsers'] = $boosts;
            }
        }
        if (\count($boostedEntryComments) > 0) {
            $boostInfos = $this->queryBoostInfo(array_keys($boostedEntryComments), 'EntryComment', $userCache);
            foreach ($boostInfos as $id => $boosts) {
                $boostedEntryComments[$id]->extendedContentProperties['boostUsers'] = $boosts;
            }
        }
        if (\count($boostedPostComments) > 0) {
            $boostInfos = $this->queryBoostInfo(array_keys($boostedPostComments), 'PostComment', $userCache);
            foreach ($boostInfos as $id => $boosts) {
                $boostedPostComments[$id]->extendedContentProperties['boostUsers'] = $boosts;
            }
        }
    }

    /**
     * @param array<int, User> $userCache
     *
     * @return array [contentId => [user => User, time => DateTimeImmutable][]]
     */
    private function queryBoostInfo(array $contentIds, string $contentType, array $userCache): array
    {
        switch ($contentType) {
            case 'Entry':
                $vType = 'entry';
                $fkType = 'entry';
                break;
            case 'Post':
                $vType = 'post';
                $fkType = 'post';
                break;
            case 'EntryComment':
                $vType = 'entry_comment';
                $fkType = 'comment';
                break;
            case 'PostComment':
                $vType = 'post_comment';
                $fkType = 'comment';
                break;
            default:
                throw new \LogicException('unreachable');
        }

        if (null === $this->criteria->cachedUserFollows) {
            $sql = 'SELECT v.%fk_type%_id AS item_id, v.user_id, v.created_at FROM user_follow uf RIGHT OUTER JOIN %v_type%_vote v ON uf.following_id = v.user_id WHERE v.%fk_type%_id IN (:itemIds) AND (uf.follower_id = :loggedInUser OR v.user_id = :loggedInUser) AND v.choice = 1';
            $sql = str_replace('%v_type%', $vType, str_replace('%fk_type%', $fkType, $sql));

            $parameters = [
                'itemIds' => $contentIds,
                'loggedInUser' => $this->loggedInUser->getId(),
            ];
            $rewritten = SqlHelpers::rewriteArrayParameters($parameters, $sql);

            $boostsQuery = $this->entityManager->getConnection()->prepare($rewritten['sql']);
            foreach ($rewritten['parameters'] as $key => $value) {
                $boostsQuery->bindValue($key, $value, SqlHelpers::getSqlType($value));
            }
        } else {
            $sql = 'SELECT v.%fk_type%_id AS item_id, v.user_id, v.created_at FROM %v_type%_vote v WHERE v.%fk_type%_id IN (:itemIds) AND (v.user_id IN (:cachedUserFollows) OR v.user_id = :loggedInUser) AND v.choice = 1';
            $sql = str_replace('%v_type%', $vType, str_replace('%fk_type%', $fkType, $sql));

            $parameters = [
                'itemIds' => $contentIds,
                'loggedInUser' => $this->loggedInUser->getId(),
                'cachedUserFollows' => $this->criteria->cachedUserFollows,
            ];
            $rewritten = SqlHelpers::rewriteArrayParameters($parameters, $sql);

            $boostsQuery = $this->entityManager->getConnection()->prepare($rewritten['sql']);
            foreach ($rewritten['parameters'] as $key => $value) {
                $boostsQuery->bindValue($key, $value, SqlHelpers::getSqlType($value));
            }
        }

        $boostsInfo = $boostsQuery->executeQuery()->fetchAllAssociative();
        $boostExtensions = [];
        $usersToFetch = [];
        $itemsToFix = [];
        foreach ($boostsInfo as $row) {
            $user = $row['user_id'];
            if (isset($userCache[$user])) {
                $user = $userCache[$user];
                $fetchUser = false;
            } else {
                $usersToFetch[] = $user;
                $fetchUser = true;
            }

            $item = ['user' => $user, 'time' => new \DateTimeImmutable($row['created_at'])];
            $boostExtensions[$row['item_id']][] = &$item;

            if ($fetchUser) {
                $itemsToFix[] = &$item;
            }
        }

        if (\count($usersToFetch) > 0) {
            $users = $this->userRepository->findBy(['id' => $usersToFetch]);
            foreach ($users as $user) {
                $userCache[$user->getId()] = $user;

                foreach ($itemsToFix as $item) {
                    if ($item['user'] === $user->getId()) {
                        $item['user'] = $user;
                    }
                }
            }
        }

        foreach ($boostExtensions as $boosts) {
            usort($boosts, fn ($a, $b) => $a['time'] <=> $b['time']);
        }

        return $boostExtensions;
    }
}
