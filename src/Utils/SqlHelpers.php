<?php

declare(strict_types=1);

namespace App\Utils;

use App\Entity\MagazineBlock;
use App\Entity\User;
use App\Entity\UserBlock;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SqlHelpers
{
    public const string USER_FOLLOWS_KEY = 'cached_user_follows_';
    public const string USER_MAGAZINE_SUBSCRIPTION_KEY = 'cached_user_magazine_subscription_';
    public const string USER_MAGAZINE_MODERATION_KEY = 'cached_user_magazine_moderation_';
    public const string USER_DOMAIN_SUBSCRIPTION_KEY = 'cached_user_domain_subscription_';
    public const string USER_BLOCKS_KEY = 'cached_user_blocks_';
    public const string USER_MAGAZINE_BLOCKS_KEY = 'cached_user_magazine_block_';
    public const string USER_DOMAIN_BLOCKS_KEY = 'cached_user_domain_block_';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
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
     * @return array{sql: string, parameters: array}>
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
                if (\sizeof($newParameterNames) > 0) {
                    $newParameterName = join(',', $newParameterNames);
                    $newSql = str_replace(":$name", $newParameterName, $newSql);
                } else {
                    // for dealing with empty array parameters we put a -1 in there,
                    // because just an empty `IN ()` will throw a syntax error
                    $newParameters[$name] = -1;
                }
            } else {
                $newParameters[$name] = $value;
            }
        }

        return [
            'parameters' => $newParameters,
            'sql' => $newSql,
        ];
    }

    public static function invertOrderings(array $orderings): array
    {
        $newOrderings = [];
        foreach ($orderings as $ordering) {
            if (str_contains($ordering, 'DESC')) {
                $newOrderings[] = str_replace('DESC', 'ASC', $ordering);
            } elseif (str_contains($ordering, 'ASC')) {
                $newOrderings[] = str_replace('ASC', 'DESC', $ordering);
            } else {
                // neither ASC nor DESC means ASC
                $newOrderings[] = $ordering.' DESC';
            }
        }

        return $newOrderings;
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

    /**
     * @return int[] the ids of the users $user follows
     */
    public function getCachedUserFollows(User $user): array
    {
        try {
            $sql = 'SELECT following_id FROM user_follow WHERE follower_id = :uId';
            if ('test' === $this->kernel->getEnvironment()) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            }

            return $this->cache->get(self::USER_FOLLOWS_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            });
        } catch (InvalidArgumentException|Exception $exception) {
            $this->logger->error('There was an error getting the cached magazine blocks of user "{u}": {e} - {m}', ['u' => $user->username, 'e' => \get_class($exception), 'm' => $exception->getMessage()]);

            return [];
        }
    }

    public function clearCachedUserFollows(User $user): void
    {
        $this->logger->debug('Clearing cached user follows for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_FOLLOWS_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached user follows of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the magazines $user is subscribed to
     */
    public function getCachedUserSubscribedMagazines(User $user): array
    {
        try {
            $sql = 'SELECT magazine_id FROM magazine_subscription WHERE user_id = :uId';
            if ('test' === $this->kernel->getEnvironment()) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            }

            return $this->cache->get(self::USER_MAGAZINE_SUBSCRIPTION_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            });
        } catch (InvalidArgumentException|Exception $exception) {
            $this->logger->error('There was an error getting the cached magazine blocks of user "{u}": {e} - {m}', ['u' => $user->username, 'e' => \get_class($exception), 'm' => $exception->getMessage()]);

            return [];
        }
    }

    public function clearCachedUserSubscribedMagazines(User $user): void
    {
        $this->logger->debug('Clearing cached magazine subscriptions for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_MAGAZINE_SUBSCRIPTION_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached subscribed Magazines of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the magazines $user moderates
     */
    public function getCachedUserModeratedMagazines(User $user): array
    {
        try {
            $sql = 'SELECT magazine_id FROM moderator WHERE user_id = :uId';
            if ('test' === $this->kernel->getEnvironment()) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            }

            return $this->cache->get(self::USER_MAGAZINE_MODERATION_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            });
        } catch (InvalidArgumentException|Exception $exception) {
            $this->logger->error('There was an error getting the cached magazine blocks of user "{u}": {e} - {m}', ['u' => $user->username, 'e' => \get_class($exception), 'm' => $exception->getMessage()]);

            return [];
        }
    }

    public function clearCachedUserModeratedMagazines(User $user): void
    {
        $this->logger->debug('Clearing cached moderated magazines for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_MAGAZINE_MODERATION_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached moderated magazines of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the domains $user is subscribed to
     */
    public function getCachedUserSubscribedDomains(User $user): array
    {
        try {
            $sql = 'SELECT domain_id FROM domain_subscription WHERE user_id = :uId';
            if ('test' === $this->kernel->getEnvironment()) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            }

            return $this->cache->get(self::USER_DOMAIN_SUBSCRIPTION_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            });
        } catch (InvalidArgumentException|Exception $exception) {
            $this->logger->error('There was an error getting the cached magazine blocks of user "{u}": {e} - {m}', ['u' => $user->username, 'e' => \get_class($exception), 'm' => $exception->getMessage()]);

            return [];
        }
    }

    public function clearCachedUserSubscribedDomains(User $user): void
    {
        $this->logger->debug('Clearing cached domain subscriptions for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_DOMAIN_SUBSCRIPTION_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached subscribed domains of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the domains $user is subscribed to
     */
    public function getCachedUserBlocks(User $user): array
    {
        try {
            $sql = 'SELECT blocked_id FROM user_block WHERE blocker_id = :uId';
            if ('test' === $this->kernel->getEnvironment()) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            }

            return $this->cache->get(self::USER_BLOCKS_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            });
        } catch (InvalidArgumentException|Exception $exception) {
            $this->logger->error('There was an error getting the cached magazine blocks of user "{u}": {e} - {m}', ['u' => $user->username, 'e' => \get_class($exception), 'm' => $exception->getMessage()]);

            return [];
        }
    }

    public function clearCachedUserBlocks(User $user): void
    {
        $this->logger->debug('Clearing cached user blocks for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_BLOCKS_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached blocked user of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the domains $user is subscribed to
     */
    public function getCachedUserMagazineBlocks(User $user): array
    {
        try {
            $sql = 'SELECT magazine_id FROM magazine_block WHERE user_id = :uId';
            if ('test' === $this->kernel->getEnvironment()) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            }

            return $this->cache->get(self::USER_MAGAZINE_BLOCKS_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            });
        } catch (InvalidArgumentException|Exception $exception) {
            $this->logger->error('There was an error getting the cached magazine blocks of user "{u}": {e} - {m}', ['u' => $user->username, 'e' => \get_class($exception), 'm' => $exception->getMessage()]);

            return [];
        }
    }

    public function clearCachedUserMagazineBlocks(User $user): void
    {
        $this->logger->debug('Clearing cached magazine blocks for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_MAGAZINE_BLOCKS_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached blocked magazines of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the domains $user is subscribed to
     */
    public function getCachedUserDomainBlocks(User $user): array
    {
        try {
            $sql = 'SELECT domain_id FROM domain_block WHERE user_id = :uId';
            if ('test' === $this->kernel->getEnvironment()) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            }

            return $this->cache->get(self::USER_DOMAIN_BLOCKS_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
                return $this->fetchSingleColumnAsArray($sql, $user);
            });
        } catch (InvalidArgumentException|Exception $exception) {
            $this->logger->error('There was an error getting the cached magazine blocks of user "{u}": {e} - {m}', ['u' => $user->username, 'e' => \get_class($exception), 'm' => $exception->getMessage()]);

            return [];
        }
    }

    public function clearCachedUserDomainBlocks(User $user): void
    {
        $this->logger->debug('Clearing cached domain blocks for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_DOMAIN_BLOCKS_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached blocked domains of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @param string $sql the sql to fetch the single column, should contain a 'uId' Parameter
     *
     * @return int[]
     *
     * @throws Exception
     */
    public function fetchSingleColumnAsArray(string $sql, User $user): array
    {
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['uId' => $user->getId()]);
        $rows = $result->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $row[array_key_first($row)];
        }

        $this->logger->debug('Fetching single column row from {sql}: {res}', ['sql' => $sql, 'res' => $result]);

        return $result;
    }

    public static function getSqlType(mixed $value): mixed
    {
        if ($value instanceof \DateTimeImmutable) {
            return Types::DATETIMETZ_IMMUTABLE;
        } elseif ($value instanceof \DateTime) {
            return Types::DATETIMETZ_MUTABLE;
        } elseif (\is_int($value)) {
            return Types::INTEGER;
        }

        return ParameterType::STRING;
    }
}
