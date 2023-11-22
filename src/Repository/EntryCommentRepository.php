<?php

// SPDX-FileCopyrightText: Copyright (c) 2016-2017 Emma <emma1312@protonmail.ch>
//
// SPDX-License-Identifier: Zlib

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\DomainBlock;
use App\Entity\DomainSubscription;
use App\Entity\EntryComment;
use App\Entity\EntryCommentFavourite;
use App\Entity\MagazineBlock;
use App\Entity\MagazineSubscription;
use App\Entity\Moderator;
use App\Entity\User;
use App\Entity\UserBlock;
use App\Entity\UserFollow;
use App\Repository\Contract\TagRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method EntryComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method EntryComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method EntryComment[]    findAll()
 * @method EntryComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntryCommentRepository extends ServiceEntityRepository implements TagRepositoryInterface
{
    public const SORT_DEFAULT = 'active';
    public const PER_PAGE = 15;

    private Security $security;

    public function __construct(ManagerRegistry $registry, Security $security)
    {
        parent::__construct($registry, EntryComment::class);

        $this->security = $security;
    }

    public function findByCriteria(Criteria $criteria): PagerfantaInterface
    {
        $adapter = new QueryAdapter($this->getEntryQueryBuilder($criteria), false);
        $pagerfanta = new Pagerfanta($adapter);

        try {
            $pagerfanta
                ->setMaxPerPage($criteria->perPage ?? self::PER_PAGE)
                ->setCurrentPage($criteria->page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    private function getEntryQueryBuilder(Criteria $criteria): QueryBuilder
    {
        $user = $this->security->getUser();

        $qb = $this->createQueryBuilder('c')
            ->select('c', 'u')
            ->join('c.user', 'u')
            ->andWhere('c.visibility IN (:visibility)')
            ->andWhere('u.visibility IN (:visible)');

        if ($user && VisibilityInterface::VISIBILITY_VISIBLE === $criteria->visibility) {
            $qb->orWhere(
                $qb->expr()->in(
                    'c.user',
                    $this->createQueryBuilder('cuf')
                        ->select('IDENTITY(cuf.following)')
                        ->from(UserFollow::class, 'cuf')
                        ->where('cuf.follower = :cUser AND c.visibility = :cVisibility')
                        ->getDQL()
                )
            )
            ->setParameter('cUser', $user)
            ->setParameter('cVisibility', VisibilityInterface::VISIBILITY_PRIVATE);
        }

        $qb->setParameter(
            'visibility',
            [
                VisibilityInterface::VISIBILITY_SOFT_DELETED,
                VisibilityInterface::VISIBILITY_VISIBLE,
                VisibilityInterface::VISIBILITY_TRASHED,
            ]
        )
        ->setParameter('visible', VisibilityInterface::VISIBILITY_VISIBLE);

        $this->addTimeClause($qb, $criteria);
        $this->filter($qb, $criteria);

        return $qb;
    }

    private function addTimeClause(QueryBuilder $qb, Criteria $criteria): void
    {
        if (Criteria::TIME_ALL !== $criteria->time) {
            $qb->andWhere('c.createdAt > :time')
                ->setParameter('time', $criteria->getSince(), Types::DATETIMETZ_IMMUTABLE);
        }
    }

    private function filter(QueryBuilder $qb, Criteria $criteria): QueryBuilder
    {
        $user = $this->security->getUser();

        if (Criteria::AP_LOCAL === $criteria->federation) {
            $qb->andWhere('c.apId IS NULL');
        }

        if ($criteria->entry) {
            $qb->andWhere('c.entry = :entry')
                ->setParameter('entry', $criteria->entry);
        }

        if ($criteria->magazine) {
            $qb->join('c.entry', 'e', Join::WITH, 'e.magazine = :magazine AND e.visibility = :visible')
                ->setParameters(['magazine' => $criteria->magazine, 'visible' => VisibilityInterface::VISIBILITY_VISIBLE]);
        } else {
            $qb->join('c.entry', 'e', Join::WITH, 'e.visibility = :visible')
                ->setParameter('visible', VisibilityInterface::VISIBILITY_VISIBLE);
        }

        if ($criteria->user) {
            $qb->andWhere('c.user = :user')
                ->setParameter('user', $criteria->user);
        }

        $qb->join('c.entry', 'ce');

        if ($criteria->domain) {
            $qb->join('ce.domain', 'ced')
                ->andWhere('ced.name = :domain')
                ->setParameter('domain', $criteria->domain);
        }

        if ($criteria->languages) {
            $qb->andWhere('c.lang IN (:languages)')
                ->setParameter('languages', $criteria->languages, ArrayParameterType::STRING);
        }

        if ($criteria->tag) {
            $qb->andWhere("JSONB_CONTAINS(c.tags, '\"".$criteria->tag."\"') = true");
        }

        if ($criteria->subscribed) {
            $qb
                ->leftJoin(MagazineSubscription::class, 'ms', Join::WITH, 'c.magazine = IDENTITY(ms.magazine) AND ms.user = :follower')
                ->leftJoin(UserFollow::class, 'uf', Join::WITH, 'c.user = IDENTITY(uf.following) AND uf.follower = :follower')
                ->leftJoin(DomainSubscription::class, 'ds', Join::WITH, 'ce.domain = IDENTITY(ds.domain) AND ds.user = :follower')
                ->andWhere('c.user = :follower')
                ->setParameter('follower', $user);
        }

        if ($criteria->moderated) {
            $qb->andWhere('c.magazine IN (SELECT IDENTITY(cm.magazine) FROM '.Moderator::class.' cm WHERE cm.user = :user)');
            $qb->setParameter('user', $this->security->getUser());
        }

        if ($criteria->favourite) {
            $qb->andWhere('c.id IN (SELECT IDENTITY(cf.entryComment) FROM '.EntryCommentFavourite::class.' cf WHERE cf.user = :user)');
            $qb->setParameter('user', $this->security->getUser());
        }

        if ($user && (!$criteria->magazine || !$criteria->magazine->userIsModerator($user)) && !$criteria->moderated) {
            $qb->andWhere(
                'c.user NOT IN (SELECT IDENTITY(ub.blocked) FROM '.UserBlock::class.' ub WHERE ub.blocker = :blocker)'
            );

            $qb->andWhere(
                'ce.user NOT IN (SELECT IDENTITY(ubc.blocked) FROM '.UserBlock::class.' ubc WHERE ubc.blocker = :blocker)'
            );

            $qb->andWhere(
                'c.magazine NOT IN (SELECT IDENTITY(mb.magazine) FROM '.MagazineBlock::class.' mb WHERE mb.user = :blocker)'
            );

            if (!$criteria->domain) {
                $qb->andWhere(
                    'ce.domain IS null OR ce.domain NOT IN (SELECT IDENTITY(db.domain) FROM '.DomainBlock::class.' db WHERE db.user = :blocker)'
                );
            }

            $qb->setParameter('blocker', $user);
        }

        if ($criteria->onlyParents) {
            $qb->andWhere('c.parent IS NULL');
        }

        if (!$user || $user->hideAdult) {
            $qb->join('e.magazine', 'm')
               ->orWhere('m.isAdult = :isAdult')
               ->orWhere('e.isAdult = :isAdult')
               ->setParameter('isAdult', false);
        }

        $sortOptions = [
            Criteria::SORT_HOT => 'c.upVotes',
            Criteria::SORT_TOP => 'c.upVotes + c.favouriteCount - c.downVotes',
            Criteria::SORT_ACTIVE => 'c.lastActive',
            Criteria::SORT_NEW => 'c.createdAt',
            Criteria::SORT_OLD => 'c.createdAt',
        ];

        $orderBy = $sortOptions[$criteria->sortOption] ?? 'c.lastActive';
        $orderDirection = \in_array($criteria->sortOption, [Criteria::SORT_NEW, Criteria::SORT_OLD]) ? 'DESC' : 'ASC';

        $qb->orderBy($orderBy, $orderDirection);

        $qb->addOrderBy('c.createdAt', 'DESC');
        $qb->addOrderBy('c.id', 'DESC');

        return $qb;
    }

    public function hydrateChildren(EntryComment ...$comments): void
    {
        if (empty($comments)) {
            return; // No need to query if there are no comments
        }

        $ids = array_map(fn (EntryComment $comment) => $comment->getRoot(), $comments);

        $children = $this->createQueryBuilder('c')
            ->andWhere('c.root IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $this->hydrate(...$children);
    }

    public function hydrate(EntryComment ...$comments): void
    {
        if (empty($comments)) {
            return; // No need to query if there are no comments
        }

        $qb = $this->createQueryBuilder('c');
        $qb
            ->select('PARTIAL c.{id}')
            ->addSelect('u', 'e', 'v', 'em', 'f')
            ->join('c.user', 'u')
            ->join('c.entry', 'e')
            ->join('c.votes', 'v')
            ->leftJoin('c.favourites', 'f')
            ->join('e.magazine', 'em')
            ->where($qb->expr()->in('c', ':comments'))
            ->setParameter('comments', $comments);

        // Left join for child comments
        $qb
            ->addSelect('cc', 'ccu', 'ccua', 'ccv', 'ccf')
            ->leftJoin('c.children', 'cc')
            ->join('cc.user', 'ccu')
            ->leftJoin('ccu.avatar', 'ccua')
            ->leftJoin('cc.votes', 'ccv')
            ->leftJoin('cc.favourites', 'ccf');

        $qb
            ->andWhere($qb->expr()->in('c', ':comments'))
            ->setParameter('comments', $comments);

        $qb->getQuery()->execute();
    }

    public function hydrateParents(EntryComment ...$comments): void
    {
        if (empty($comments)) {
            return; // No need to query if there are no comments
        }

        $qb = $this->createQueryBuilder('c');

        $qb
            ->select('PARTIAL c.{id}', 'cp', 'cpu', 'cpe')
            ->leftJoin('c.parent', 'cp')
            ->leftJoin('cp.user', 'cpu')
            ->leftJoin('cp.entry', 'cpe')
            ->where($qb->expr()->in('c', ':comments'))
            ->setParameter('comments', $comments)
            ->getQuery()
            ->execute();
    }

    public function findToDelete(User $user, int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.visibility != :visibility')
            ->andWhere('c.user = :user')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_SOFT_DELETED)
            ->setParameter('user', $user)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findWithTags(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.tags IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
