<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Poll;
use App\Entity\PollVote;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Poll>
 *
 * @method Poll|null find($id, $lockMode = null, $lockVersion = null)
 * @method Poll|null findOneBy(array $criteria, array $orderBy = null)
 * @method Poll|null findOneByUrl(string $url)
 * @method Poll[]    findAll()
 * @method Poll[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PollRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 25;

    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Poll::class);
    }

    /**
     * @return User[]
     */
    public function getAllLocalVotersOfPoll(Poll $poll): array
    {
        $localVotes = array_filter($poll->votes->toArray(), fn (PollVote $vote) => null === $vote->voter->apId);

        return array_map(fn (PollVote $vote) => $vote->voter, $localVotes);
    }

    /**
     * @return Poll[]
     */
    public function getAllEndedPollsToSentNotifications(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.endDate <= :now')
            ->andWhere('p.sentNotifications = false')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }
}
