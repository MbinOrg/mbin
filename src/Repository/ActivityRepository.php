<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Pagination\Pagerfanta;
use App\Pagination\QueryAdapter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\PagerfantaInterface;

/**
 * @method Activity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Activity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Activity|null findOneByName(string $name)
 * @method Activity[]    findAll()
 * @method Activity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($registry, Activity::class);
    }

    public function findFirstActivitiesByTypeAndObject(string $type, ActivityPubActivityInterface|ActivityPubActorInterface $object): ?Activity
    {
        $results = $this->findAllActivitiesByTypeAndObject($type, $object);
        if (!empty($results)) {
            return $results[0];
        }

        return null;
    }

    /**
     * @return Activity[]|null
     */
    public function findAllActivitiesByTypeAndObject(string $type, ActivityPubActivityInterface|ActivityPubActorInterface $object): ?array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where('a.type = :type');
        $qb->setParameter('type', $type);

        $this->addObjectFilter($qb, $object);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Activity[]|null
     */
    public function findAllActivitiesByObject(ActivityPubActivityInterface|ActivityPubActorInterface $object): ?array
    {
        $qb = $this->createQueryBuilder('a');

        $this->addObjectFilter($qb, $object);

        return $qb->getQuery()->getResult();
    }

    private function addObjectFilter(QueryBuilder $qb, ActivityPubActivityInterface|ActivityPubActorInterface $object): void
    {
        if ($object instanceof Entry) {
            $qb->andWhere('a.objectEntry = :entry')
                ->setParameter('entry', $object);
        } elseif ($object instanceof EntryComment) {
            $qb->andWhere('a.objectEntryComment = :entryComment')
                ->setParameter('entryComment', $object);
        } elseif ($object instanceof Post) {
            $qb->andWhere('a.objectPost = :post')
                ->setParameter('post', $object);
        } elseif ($object instanceof PostComment) {
            $qb->andWhere('a.objectPostComment = :postComment')
                ->setParameter('postComment', $object);
        } elseif ($object instanceof Message) {
            $qb->andWhere('a.objectMessage = :message')
                ->setParameter('message', $object);
        } elseif ($object instanceof User) {
            $qb->andWhere('a.objectUser = :user')
                ->setParameter('user', $object);
        } elseif ($object instanceof Magazine) {
            $qb->andWhere('a.objectMagazine = :magazine')
                ->setParameter('magazine', $object);
        }
    }

    public function getOutboxActivitiesOfUser(User $user): PagerfantaInterface
    {
        if ($user->isDeleted || $user->isBanned || $user->isTrashed() || null !== $user->markedForDeletionAt) {
            return new Pagerfanta(new ArrayAdapter([]));
        }

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.audience', 'm')
            ->leftJoin('a.objectEntry', 'e')
            ->leftJoin('a.objectEntryComment', 'ec')
            ->leftJoin('a.objectPost', 'p')
            ->leftJoin('a.objectPostComment', 'pc')
            ->where('a.userActor = :user')
            ->andWhere('a.type IN (:types)')
            ->andWhere('a.objectMessage IS NULL') // chat messages are not public
            ->andWhere('m IS NULL OR m.visibility = :visible')
            ->andWhere('e IS NULL OR e.visibility = :visible')
            ->andWhere('ec IS NULL OR ec.visibility = :visible')
            ->andWhere('p IS NULL OR p.visibility = :visible')
            ->andWhere('pc IS NULL OR pc.visibility = :visible')
            ->setParameter('visible', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('user', $user)
            ->setParameter('types', ['Create', 'Announce'])
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.uuid', 'DESC');

        return new Pagerfanta(new QueryAdapter($qb));
    }

    public function createForRemotePayload(array $payload, ActivityPubActivityInterface|Entry|EntryComment|Post|PostComment|ActivityPubActorInterface|User|Magazine|Activity|array|string|null $object = null): Activity
    {
        if (isset($payload['@context'])) {
            unset($payload['@context']);
        }
        $activity = new Activity($payload['type']);
        $activity->activityJson = json_encode($payload['object']);
        $activity->isRemote = true;
        if (null !== $object) {
            $activity->setObject($object);
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    public function createForRemoteActivity(array $payload, ActivityPubActivityInterface|Entry|EntryComment|Post|PostComment|ActivityPubActorInterface|User|Magazine|Activity|array|string|null $object = null): Activity
    {
        if (isset($payload['@context'])) {
            unset($payload['@context']);
        }
        $activity = new Activity($payload['type']);
        $nestedTypes = ['Announce', 'Accept', 'Reject', 'Add', 'Remove'];
        if (\in_array($payload['type'], $nestedTypes) && isset($payload['object']) && \is_array($payload['object'])) {
            $activity->innerActivity = $this->createForRemoteActivity($payload['object'], $object);
        } else {
            $activity->activityJson = json_encode($payload);
        }
        $activity->isRemote = true;
        if (null !== $object) {
            $activity->setObject($object);
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}
