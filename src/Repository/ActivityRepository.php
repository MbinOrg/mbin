<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Activity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Activity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Activity|null findOneByName(string $name)
 * @method Activity[]    findAll()
 * @method Activity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
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

        return $qb->getQuery()->getResult();
    }
}
