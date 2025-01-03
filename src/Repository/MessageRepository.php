<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Message;
use App\PageView\MessageThreadPageView;
use App\Service\SettingsManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message|null findOneByName(string $name)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 25;

    public function __construct(ManagerRegistry $registry, private readonly SettingsManager $settingsManager)
    {
        parent::__construct($registry, Message::class);
    }

    public function findByCriteria(MessageThreadPageView|Criteria $criteria): Pagerfanta
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.thread = :m_thread_id')
            ->setParameter('m_thread_id', $criteria->thread->getId());

        switch ($criteria->sortOption) {
            case Criteria::SORT_OLD:
                $qb->orderBy('m.createdAt', 'ASC');
                break;
            default:
                $qb->orderBy('m.createdAt', 'DESC');
        }

        $messages = new Pagerfanta(
            new QueryAdapter(
                $qb,
                false
            )
        );

        try {
            $messages->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
            $messages->setCurrentPage($criteria->page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $messages;
    }

    public function findLastMessageBefore(Message $message): ?Message
    {
        $results = $this->createQueryBuilder('m')
            ->where('m.createdAt < :previous_message')
            ->andWhere('m.thread = :thread')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->setParameter('previous_message', $message->createdAt)
            ->setParameter('thread', $message->thread)
            ->getQuery()
            ->getResult();

        if (1 === \sizeof($results)) {
            return $results[0];
        }

        return null;
    }

    public function findByApId(string $apId): ?Message
    {
        if ($this->settingsManager->isLocalUrl($apId)) {
            $path = parse_url($apId, PHP_URL_PATH);
            preg_match('/\/messages\/([\w\-]+)/', $path, $matches);
            if (2 === \sizeof($matches)) {
                $uuid = $matches[1];

                return $this->findOneBy(['uuid' => $uuid]);
            }
        } else {
            return $this->findOneBy(['apId' => $apId]);
        }

        return null;
    }
}
