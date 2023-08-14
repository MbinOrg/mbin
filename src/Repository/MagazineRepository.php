<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\MagazineSubscription;
use App\Entity\Moderator;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\PageView\MagazinePageView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\Collections\CollectionAdapter;
use Pagerfanta\Doctrine\Collections\SelectableAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method Magazine|null find($id, $lockMode = null, $lockVersion = null)
 * @method Magazine|null findOneBy(array $criteria, array $orderBy = null)
 * @method Magazine[]    findAll()
 * @method Magazine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MagazineRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 48;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Magazine::class);
    }

    public function save(Magazine $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByName(?string $name): ?Magazine
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.visibility = :visibility')
            ->andWhere('LOWER(m.name) = LOWER(:name)')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPaginated(MagazinePageView $criteria): PagerfantaInterface
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE);

        if ($criteria->query) {
            $restrictions = 'LOWER(m.name) LIKE LOWER(:q) OR LOWER(m.title) LIKE LOWER(:q)';

            if ($criteria->fields === $criteria::FIELDS_NAMES_DESCRIPTIONS) {
                $restrictions .= ' OR LOWER(m.description) LIKE LOWER(:q)';
            }

            $qb->andWhere($restrictions)
                ->setParameter('q', '%'.trim($criteria->query).'%');
        }

        if ($criteria->showOnlyLocalMagazines()) {
            $qb->andWhere('m.apId IS NULL');
        }

        match ($criteria->adult) {
            $criteria::ADULT_HIDE => $qb->andWhere('m.isAdult = false'),
            $criteria::ADULT_ONLY => $qb->andWhere('m.isAdult = true'),
            $criteria::ADULT_SHOW => true,
        };

        match ($criteria->sortOption) {
            default => $qb->addOrderBy('m.subscriptionsCount', 'DESC'),
            $criteria::SORT_ACTIVE => $qb->addOrderBy('m.lastActive', 'DESC'),
            $criteria::SORT_NEW => $qb->addOrderBy('m.createdAt', 'DESC'),
            $criteria::SORT_THREADS => $qb->addOrderBy('m.entryCount', 'DESC'),
            $criteria::SORT_COMMENTS => $qb->addOrderBy('m.entryCommentCount', 'DESC'),
            $criteria::SORT_POSTS => $qb->addOrderBy('m.postCount + m.postCommentCount', 'DESC'),
        };

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));

        try {
            $pagerfanta->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
            $pagerfanta->setCurrentPage($criteria->page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findSubscribedMagazines(int $page, User $user): PagerfantaInterface
    {
        $pagerfanta = new Pagerfanta(
            new CollectionAdapter(
                $user->subscriptions
            )
        );

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findBlockedMagazines(int $page, User $user): PagerfantaInterface
    {
        $pagerfanta = new Pagerfanta(
            new CollectionAdapter(
                $user->blockedMagazines
            )
        );

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }
        try {
            $pagerfanta->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findModerators(Magazine $magazine, ?int $page = 1): PagerfantaInterface
    {
        $criteria = Criteria::create()->orderBy(['createdAt' => 'ASC']);

        $moderators = new Pagerfanta(new SelectableAdapter($magazine->moderators, $criteria));
        $moderators->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
        $moderators->setCurrentPage($page);

        return $moderators;
    }

    public function findModlog(Magazine $magazine, ?int $page = 1): PagerfantaInterface
    {
        $criteria = Criteria::create()->orderBy(['createdAt' => 'DESC']);

        $moderators = new Pagerfanta(new SelectableAdapter($magazine->logs, $criteria));
        $moderators->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
        $moderators->setCurrentPage($page);

        return $moderators;
    }

    public function findBans(Magazine $magazine, ?int $page = 1): PagerfantaInterface
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->gt('expiredAt', new \DateTime()))
            ->orWhere(Criteria::expr()->isNull('expiredAt'))
            ->orderBy(['createdAt' => 'DESC']);

        $bans = new Pagerfanta(new SelectableAdapter($magazine->bans, $criteria));
        $bans->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
        $bans->setCurrentPage($page);

        return $bans;
    }

    public function findReports(Magazine $magazine, ?string $status, ?int $page = 1): PagerfantaInterface
    {
        $criteria = Criteria::create();
        if ($status) {
            $criteria->andWhere(Criteria::expr()->eq('status', $status));
        }
        $criteria->orderBy(['weight' => 'ASC', 'createdAt' => 'DESC']);

        $bans = new Pagerfanta(new SelectableAdapter($magazine->reports, $criteria));
        $bans->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
        $bans->setCurrentPage($page);

        return $bans;
    }

    public function findBadges(Magazine $magazine): Collection
    {
        return $magazine->badges;
    }

    public function findModeratedMagazines(User $user, ?int $page = 1): PagerfantaInterface
    {
        $dql =
            'SELECT m FROM '.Magazine::class.' m WHERE m IN ('.
            'SELECT IDENTITY(md.magazine) FROM '.Moderator::class.' md WHERE md.user = :user) ORDER BY m.apId DESC, m.lastActive DESC';

        $query = $this->getEntityManager()->createQuery($dql)
            ->setParameter('user', $user);

        $pagerfanta = new Pagerfanta(
            new QueryAdapter(
                $query
            )
        );

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findTrashed(int $page, Magazine $magazine): PagerfantaInterface
    {
        // @todo union adapter
        $conn = $this->_em->getConnection();

        $magazineId = $magazine->getId();
        $sql = '
            (SELECT id, last_active, magazine_id, \'entry\' AS type FROM entry WHERE magazine_id = :magazineId AND visibility = \'trashed\')
            UNION
            (SELECT id, last_active, magazine_id, \'entry_comment\' AS type FROM entry_comment WHERE magazine_id = :magazineId AND visibility = \'trashed\')
            UNION
            (SELECT id, last_active, magazine_id, \'post\' AS type FROM post WHERE magazine_id = :magazineId AND visibility = \'trashed\')
            UNION
            (SELECT id, last_active, magazine_id, \'post_comment\' AS type FROM post_comment WHERE magazine_id = :magazineId AND visibility = \'trashed\')
            ORDER BY last_active DESC';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['magazineId' => $magazineId]);

        $pagerfanta = new Pagerfanta(
            new ArrayAdapter(
                $stmt->fetchAllAssociative()
            )
        );

        $countAll = $pagerfanta->count();

        try {
            $pagerfanta->setMaxPerPage(20000);
            $pagerfanta->setCurrentPage(1);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        $result = $pagerfanta->getCurrentPageResults();

        $entries = $this->_em->getRepository(Entry::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'entry')]
        );
        $entryComments = $this->_em->getRepository(EntryComment::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'entry_comment')]
        );
        $post = $this->_em->getRepository(Post::class)->findBy(['id' => $this->getOverviewIds((array) $result, 'post')]);
        $postComment = $this->_em->getRepository(PostComment::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'post_comment')]
        );

        $result = array_merge($entries, $entryComments, $post, $postComment);
        uasort($result, fn ($a, $b) => $a->getCreatedAt() > $b->getCreatedAt() ? -1 : 1);

        $pagerfanta = new Pagerfanta(
            new ArrayAdapter(
                $result
            )
        );

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
            $pagerfanta->setMaxNbPages($countAll > 0 ? ((int) ceil($countAll / self::PER_PAGE)) : 1);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    private function getOverviewIds(array $result, string $type): array
    {
        $result = array_filter($result, fn ($subject) => $subject['type'] === $type);

        return array_map(fn ($subject) => $subject['id'], $result);
    }

    public function findAudience(Magazine $magazine): array
    {
        $dql =
            'SELECT COUNT(u.id), u.apInboxUrl FROM '.User::class.' u WHERE u IN ('.
            'SELECT IDENTITY(ms.user) FROM '.MagazineSubscription::class.' ms WHERE ms.magazine = :magazine)'.
            'AND u.apId IS NOT NULL AND u.isBanned = false AND u.apTimeoutAt IS NULL '.
            'GROUP BY u.apInboxUrl';

        $res = $this->getEntityManager()->createQuery($dql)
            ->setParameter('magazine', $magazine)
            ->getResult();

        return array_map(fn ($item) => $item['apInboxUrl'], $res);
    }

    public function findWithoutKeys(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.privateKey IS NULL')
            ->andWhere('m.apId IS NULL')
            ->getQuery()
            ->getResult();
    }

    public function findByTag($tag): ?Magazine
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.name = :tag')
            ->orWhere("m.tags IS NOT NULL AND JSONB_CONTAINS(m.tags, '\"".$tag."\"') = true")
            ->orderBy('m.lastActive', 'DESC')
            ->setMaxResults(1)
            ->setParameter('tag', $tag)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByActivity()
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.postCount > 0')
            ->orWhere('m.entryCount > 0')
            ->orderBy('m.postCount', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    public function findByApGroupProfileId(array $apIds): ?Magazine
    {
        return $this->createQueryBuilder('m')
            ->where('m.apProfileId IN (?1)')
            ->setParameter(1, $apIds)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRandom(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT id FROM magazine
            WHERE ap_id IS NULL
            ORDER BY random()
            LIMIT 5
            ';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery();
        $ids = $stmt->fetchAllAssociative();

        return $this->createQueryBuilder('m')
            ->where('m.id IN (:ids)')
            ->andWhere('m.isAdult = false')
            ->andWhere('m.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findRelated(string $magazine): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.entryCount > 0 OR m.postCount > 0')
            ->andWhere('m.title LIKE :magazine OR m.description LIKE :magazine OR m.name LIKE :magazine')
            ->andWhere('m.isAdult = false')
            ->andWhere('m.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('magazine', "%{$magazine}%")
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    public function findRemoteForUpdate(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.apId IS NOT NULL')
            ->andWhere('m.apDomain IS NULL')
            ->andWhere('m.apDeletedAt IS NULL')
            ->andWhere('m.apTimeoutAt IS NULL')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();
    }
}
