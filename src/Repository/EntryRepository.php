<?php

// SPDX-FileCopyrightText: Copyright (c) 2016-2017 Emma <emma1312@protonmail.ch>
//
// SPDX-License-Identifier: Zlib

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\DomainBlock;
use App\Entity\DomainSubscription;
use App\Entity\Entry;
use App\Entity\EntryFavourite;
use App\Entity\HashtagLink;
use App\Entity\Magazine;
use App\Entity\MagazineBlock;
use App\Entity\MagazineSubscription;
use App\Entity\Moderator;
use App\Entity\User;
use App\Entity\UserBlock;
use App\Entity\UserFollow;
use App\PageView\EntryPageView;
use App\Pagination\AdapterFactory;
use App\Service\SettingsManager;
use App\Utils\SqlHelpers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @extends ServiceEntityRepository<Entry>
 *
 * @method Entry|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entry|null findOneBy(array $criteria, array $orderBy = null)
 * @method Entry|null findOneByUrl(string $url)
 * @method Entry[]    findAll()
 * @method Entry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntryRepository extends ServiceEntityRepository
{
    public const SORT_DEFAULT = 'hot';
    public const TIME_DEFAULT = Criteria::TIME_ALL;
    public const PER_PAGE = 25;

    public function __construct(
        ManagerRegistry $registry,
        private readonly Security $security,
        private readonly CacheInterface $cache,
        private readonly AdapterFactory $adapterFactory,
        private readonly SettingsManager $settingsManager,
        private readonly SqlHelpers $sqlHelpers,
    ) {
        parent::__construct($registry, Entry::class);
    }

    public function findByCriteria(EntryPageView|Criteria $criteria): Pagerfanta
    {
        $pagerfanta = new Pagerfanta($this->adapterFactory->create($this->getEntryQueryBuilder($criteria)));

        try {
            $pagerfanta->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
            $pagerfanta->setCurrentPage($criteria->page);
            if (!$criteria->magazine) {
                $pagerfanta->setMaxNbPages(1000);
            }
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    private function getEntryQueryBuilder(EntryPageView $criteria): QueryBuilder
    {
        $user = $this->security->getUser();

        $qb = $this->createQueryBuilder('e')
            ->addSelect('e', 'm', 'u', 'd')
            ->where('e.visibility = :visibility')
            ->andWhere('m.visibility = :visible')
            ->andWhere('u.visibility = :visible')
            ->andWhere('u.isDeleted = false')
            ->join('e.magazine', 'm')
            ->join('e.user', 'u')
            ->leftJoin('e.domain', 'd');

        if ($user && VisibilityInterface::VISIBILITY_VISIBLE === $criteria->visibility) {
            $qb->orWhere(
                'e.user IN (SELECT IDENTITY(euf.following) FROM '.UserFollow::class.' euf WHERE euf.follower = :euf_user AND e.visibility = :euf_visibility)'
            )
                ->setParameter('euf_user', $user)
                ->setParameter('euf_visibility', VisibilityInterface::VISIBILITY_PRIVATE);
        } else {
            $qb->orWhere('e.user IS NULL');
        }

        $qb->setParameter('visibility', $criteria->visibility)
            ->setParameter('visible', VisibilityInterface::VISIBILITY_VISIBLE);

        $this->addTimeClause($qb, $criteria);
        $this->addStickyClause($qb, $criteria);
        $this->filter($qb, $criteria);
        $this->addBannedHashtagClause($qb);

        return $qb;
    }

    private function addTimeClause(QueryBuilder $qb, EntryPageView $criteria): void
    {
        if (Criteria::TIME_ALL !== $criteria->time) {
            $since = $criteria->getSince();

            $qb->andWhere('e.createdAt > :time')
                ->setParameter('time', $since, Types::DATETIMETZ_IMMUTABLE);
        }
    }

    private function addStickyClause(QueryBuilder $qb, EntryPageView $criteria): void
    {
        if ($criteria->stickiesFirst) {
            if (1 === $criteria->page) {
                $qb->addOrderBy('e.sticky', 'DESC');
            } else {
                $qb->andWhere($qb->expr()->eq('e.sticky', 'false'));
            }
        }
    }

    private function addBannedHashtagClause(QueryBuilder $qb): void
    {
        $dql = $this->getEntityManager()->createQueryBuilder()
            ->select('hl2')
            ->from(HashtagLink::class, 'hl2')
            ->join('hl2.hashtag', 'h2')
            ->where('h2.banned = true')
            ->andWhere('hl2.entry = e')
            ->getDQL();
        $qb->andWhere(
            $qb->expr()->not(
                $qb->expr()->exists($dql)
            )
        );
    }

    private function filter(QueryBuilder $qb, EntryPageView $criteria): QueryBuilder
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if (Criteria::AP_LOCAL === $criteria->federation) {
            $qb->andWhere('e.apId IS NULL');
        } elseif (Criteria::AP_FEDERATED === $criteria->federation) {
            $qb->andWhere('e.apId IS NOT NULL');
        }

        if ($criteria->magazine) {
            $qb->andWhere('e.magazine = :magazine')
                ->setParameter('magazine', $criteria->magazine);
        }

        if ($criteria->user) {
            $qb->andWhere('e.user = :user')
                ->setParameter('user', $criteria->user);
        }

        if ($criteria->type and 'all' !== $criteria->type) {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $criteria->type);
        }

        if ($criteria->tag) {
            $qb->andWhere('t.tag = :tag')
                ->join('e.hashtags', 'h')
                ->join('h.hashtag', 't')
                ->setParameter('tag', $criteria->tag);
        }

        if ($criteria->domain) {
            $qb->andWhere('d.name = :domain')
                ->setParameter('domain', $criteria->domain);
        }

        if ($criteria->languages) {
            $qb->andWhere('e.lang IN (:languages)')
                ->setParameter('languages', $criteria->languages, ArrayParameterType::STRING);
        }

        if ($criteria->subscribed) {
            $qb->andWhere(
                'e.magazine IN (SELECT IDENTITY(ms.magazine) FROM '.MagazineSubscription::class.' ms WHERE ms.user = :user)
                OR
                e.user IN (SELECT IDENTITY(uf.following) FROM '.UserFollow::class.' uf WHERE uf.follower = :user)
                OR
                e.domain IN (SELECT IDENTITY(ds.domain) FROM '.DomainSubscription::class.' ds WHERE ds.user = :user)
                OR
                e.user = :user'
            )
                ->setParameter('user', $this->security->getUser());
        }

        if ($criteria->moderated) {
            $qb->andWhere(
                'e.magazine IN (SELECT IDENTITY(mm.magazine) FROM '.Moderator::class.' mm WHERE mm.user = :user)'
            );
            $qb->setParameter('user', $this->security->getUser());
        }

        if ($criteria->favourite) {
            $qb->andWhere(
                'e.id IN (SELECT IDENTITY(mf.entry) FROM '.EntryFavourite::class.' mf WHERE mf.user = :user)'
            );
            $qb->setParameter('user', $this->security->getUser());
        }

        if ($user && (!$criteria->magazine || !$criteria->magazine->userIsModerator($user)) && !$criteria->moderated) {
            $qb->andWhere(
                'e.user NOT IN (SELECT IDENTITY(ub.blocked) FROM '.UserBlock::class.' ub WHERE ub.blocker = :blocker)'
            );

            $qb->andWhere(
                'e.magazine NOT IN (SELECT IDENTITY(mb.magazine) FROM '.MagazineBlock::class.' mb WHERE mb.user = :blocker)'
            );

            if (!$criteria->domain) {
                $qb->andWhere(
                    'e.domain IS null OR e.domain NOT IN (SELECT IDENTITY(db.domain) FROM '.DomainBlock::class.' db WHERE db.user = :blocker)'
                );
            }

            $qb->setParameter('blocker', $user);
        }

        if (!$user || $user->hideAdult) {
            $qb->andWhere('m.isAdult = :isAdult')
                ->andWhere('e.isAdult = :isAdult')
                ->setParameter('isAdult', false);
        }

        switch ($criteria->sortOption) {
            case Criteria::SORT_TOP:
                $qb->addOrderBy('e.score', 'DESC');
                break;
            case Criteria::SORT_HOT:
                $qb->addOrderBy('e.ranking', 'DESC');
                break;
            case Criteria::SORT_COMMENTED:
                $qb->addOrderBy('e.commentCount', 'DESC');
                break;
            case Criteria::SORT_ACTIVE:
                $qb->addOrderBy('e.lastActive', 'DESC');
                break;
            default:
        }

        $qb->addOrderBy('e.createdAt', Criteria::SORT_OLD === $criteria->sortOption ? 'ASC' : 'DESC');
        $qb->addOrderBy('e.id', 'DESC');

        return $qb;
    }

    public function hydrate(Entry ...$entries): void
    {
        $this->_em->createQueryBuilder()
            ->select('PARTIAL e.{id}')
            ->addSelect('u')
            ->addSelect('ua')
            ->addSelect('m')
            ->addSelect('mi')
            ->addSelect('d')
            ->addSelect('i')
            ->addSelect('b')
            ->from(Entry::class, 'e')
            ->join('e.user', 'u')
            ->join('e.magazine', 'm')
            ->join('e.domain', 'd')
            ->leftJoin('u.avatar', 'ua')
            ->leftJoin('m.icon', 'mi')
            ->leftJoin('e.image', 'i')
            ->leftJoin('e.badges', 'b')
            ->where('e IN (?1)')
            ->setParameter(1, $entries)
            ->getQuery()
            ->getResult();

        if ($this->security->getUser()) {
            $this->_em->createQueryBuilder()
                ->select('PARTIAL e.{id}')
                ->addSelect('ev')
                ->addSelect('ef')
                ->from(Entry::class, 'e')
                ->leftJoin('e.favourites', 'ef')
                ->leftJoin('e.votes', 'ev')
                ->where('e IN (?1)')
                ->setParameter(1, $entries)
                ->getQuery()
                ->getResult();
        }
    }

    public function countEntriesByMagazine(Magazine $magazine): int
    {
        return \intval(
            $this->createQueryBuilder('e')
                ->select('count(e.id)')
                ->where('e.magazine = :magazine')
                ->andWhere('e.visibility = :visibility')
                ->setParameter('magazine', $magazine)
                ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
                ->getQuery()
                ->getSingleScalarResult()
        );
    }

    public function countEntryCommentsByMagazine(Magazine $magazine): int
    {
        return \intval(
            $this->createQueryBuilder('e')
                ->select('sum(e.commentCount)')
                ->where('e.magazine = :magazine')
                ->setParameter('magazine', $magazine)
                ->getQuery()
                ->getSingleScalarResult()
        );
    }

    public function findToDelete(User $user, int $limit): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.visibility != :visibility')
            ->andWhere('e.user = :user')
            ->setParameters(['visibility' => VisibilityInterface::VISIBILITY_SOFT_DELETED, 'user' => $user])
            ->orderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRelatedByTag(string $tag, ?int $limit = 1, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('e');

        $qb->andWhere('e.visibility = :visibility')
            ->andWhere('m.visibility = :visibility')
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.isDeleted = false')
            ->andWhere('m.isAdult = false')
            ->andWhere('e.isAdult = false')
            ->andWhere('h.tag = :tag')
            ->join('e.magazine', 'm')
            ->join('e.user', 'u')
            ->join('e.hashtags', 'hl')
            ->join('hl.hashtag', 'h')
            ->orderBy('e.createdAt', 'DESC')
            ->setParameters([
                'visibility' => VisibilityInterface::VISIBILITY_VISIBLE,
                'tag' => $tag,
            ])
            ->setMaxResults($limit);

        if (null !== $user) {
            $qb->andWhere($qb->expr()->not($qb->expr()->exists($this->sqlHelpers->getBlockedMagazinesDql($user))))
                ->andWhere($qb->expr()->not($qb->expr()->exists($this->sqlHelpers->getBlockedUsersDql($user))));
            $qb->setParameter('user', $user);
        }

        return $qb->getQuery()
            ->getResult();
    }

    public function findRelatedByMagazine(string $name, ?int $limit = 1, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('e');

        $qb->where('m.name LIKE :name OR m.title LIKE :title')
            ->andWhere('e.visibility = :visibility')
            ->andWhere('m.visibility = :visibility')
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.isDeleted = false')
            ->andWhere('m.isAdult = false')
            ->andWhere('e.isAdult = false')
            ->join('e.magazine', 'm')
            ->join('e.user', 'u')
            ->orderBy('e.createdAt', 'DESC')
            ->setParameters(
                ['name' => "%{$name}%", 'title' => "%{$name}%", 'visibility' => VisibilityInterface::VISIBILITY_VISIBLE]
            )
            ->setMaxResults($limit);

        if (null !== $user) {
            $qb->andWhere($qb->expr()->not($qb->expr()->exists($this->sqlHelpers->getBlockedMagazinesDql($user))))
                ->andWhere($qb->expr()->not($qb->expr()->exists($this->sqlHelpers->getBlockedUsersDql($user))));
            $qb->setParameter('user', $user);
        }

        return $qb->getQuery()
            ->getResult();
    }

    public function findLast(int $limit, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('e');

        $qb = $qb->where('e.isAdult = false')
            ->andWhere('e.visibility = :visibility')
            ->andWhere('m.visibility = :visibility')
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.isDeleted = false')
            ->andWhere('m.isAdult = false');
        if ($this->settingsManager->get('MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY')) {
            $qb = $qb->andWhere('m.apId IS NULL');
        }

        if (null !== $user) {
            $qb->andWhere($qb->expr()->not($qb->expr()->exists($this->sqlHelpers->getBlockedMagazinesDql($user))))
                ->andWhere($qb->expr()->not($qb->expr()->exists($this->sqlHelpers->getBlockedUsersDql($user))));
            $qb->setParameter('user', $user);
        }

        return $qb->join('e.magazine', 'm')
            ->join('e.user', 'u')
            ->orderBy('e.createdAt', 'DESC')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Entry[]
     */
    public function findPinned(Magazine $magazine): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.magazine = :m')
            ->andWhere('e.sticky = true')
            ->andWhere('e.visibility = :visibility')
            ->setParameter('m', $magazine)
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->getQuery()
            ->getResult()
        ;
    }

    private function countAll(EntryPageView|Criteria $criteria): int
    {
        return $this->cache->get(
            'entries_count_'.$criteria->magazine?->name,
            function (ItemInterface $item) use ($criteria): int {
                $item->expiresAfter(60);

                if (!$criteria->magazine) {
                    $query = $this->_em->createQuery(
                        'SELECT COUNT(p.id) FROM App\Entity\Entry p WHERE p.visibility = :visibility'
                    )
                        ->setParameter('visibility', 'visible');
                } else {
                    $query = $this->_em->createQuery(
                        'SELECT COUNT(p.id) FROM App\Entity\Entry p WHERE p.visibility = :visibility AND p.magazine = :magazine'
                    )
                        ->setParameters(['visibility' => 'visible', 'magazine' => $criteria->magazine]);
                }

                try {
                    return $query->getSingleScalarResult();
                } catch (NoResultException $e) {
                    return 0;
                }
            }
        );
    }

    public function findCross(Entry $entry): array
    {
        $qb = $this->createQueryBuilder('e');

        if (\strlen($entry->title) <= 10 && !$entry->url) {
            return [];
        }

        if ($entry->url) {
            $qb->where('e.url = :url')
                ->setParameter('url', $entry->url);
        } else {
            $qb->where('e.title = :title')
                ->setParameter('title', $entry->title);
        }

        $qb->andWhere('e.id != :id')
            ->andWhere('m.visibility = :visibility')
            ->andWhere('e.visibility = :visibility')
            ->andWhere('u.isDeleted = false')
            ->innerJoin('e.user', 'u')
            ->join('e.magazine', 'm')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('id', $entry->getId())
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(5);

        return $qb->getQuery()->getResult();
    }
}
