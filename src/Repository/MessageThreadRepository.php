<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Message;
use App\Entity\MessageThread;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method MessageThread|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageThread|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageThread|null findOneByName(string $name)
 * @method MessageThread[]    findAll()
 * @method MessageThread[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageThreadRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 25;

    public function __construct(ManagerRegistry $registry, private readonly LoggerInterface $logger)
    {
        parent::__construct($registry, MessageThread::class);
    }

    public function findUserMessages(?User $user, int $page, int $perPage = self::PER_PAGE)
    {
        $qb = $this->createQueryBuilder('mt');
        $qb->where(':user MEMBER OF mt.participants')
            ->andWhere($qb->expr()->exists('SELECT m FROM '.Message::class.' m WHERE m.thread = mt'))
            ->orderBy('mt.updatedAt', 'DESC')
            ->setParameter(':user', $user);

        $pager = new Pagerfanta(new QueryAdapter($qb));
        try {
            $pager->setMaxPerPage($perPage);
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pager;
    }

    /**
     * @param User[] $participants
     *
     * @return MessageThread[] the message threads that contain the participants and no one else, order by their updated date (last message)
     *
     * @throws Exception
     */
    public function findByParticipants(array $participants): array
    {
        $this->logger->debug('looking for thread with participants: {p}', ['p' => array_map(fn (User $u) => $u->username, $participants)]);
        $whereString = '';
        $parameters = ['ctn' => [\sizeof($participants), ParameterType::INTEGER]];
        $i = 0;
        foreach ($participants as $participant) {
            $whereString .= "AND EXISTS(SELECT * FROM message_thread_participants mtp WHERE mtp.message_thread_id = mt.id AND mtp.user_id = :p$i)";
            $parameters["p$i"] = [$participant->getId(), ParameterType::INTEGER];
            ++$i;
        }
        $sql = "SELECT mt.id FROM message_thread mt
                WHERE (SELECT COUNT(*) FROM message_thread_participants mtp WHERE mtp.message_thread_id = mt.id) = :ctn $whereString
                ORDER BY mt.updated_at DESC";
        $em = $this->getEntityManager();
        $stmt = $em->getConnection()->prepare($sql);
        foreach ($parameters as $param => $value) {
            $stmt->bindValue($param, $value[0], $value[1]);
        }
        $results = $stmt->executeQuery()->fetchAllAssociative();

        $this->logger->debug('got results for query {q}: {r}', ['q' => $sql, 'r' => $results]);
        if (\sizeof($results) > 0) {
            $ids = [];
            foreach ($results as $result) {
                $ids[] = $result['id'];
            }

            return $this->findBy(['id' => $ids], ['updatedAt' => 'DESC']);
        }

        return [];
    }
}
