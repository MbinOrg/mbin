<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Embed;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @method Embed|null find($id, $lockMode = null, $lockVersion = null)
 * @method Embed|null findOneBy(array $criteria, array $orderBy = null)
 * @method Embed|null findOneByUrl(string $url)
 * @method Embed[]    findAll()
 * @method Embed[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmbedRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($registry, Embed::class);
    }

    public function add(Embed $entity, bool $flush = true): void
    {
        // Check if embed url does not exists yet (null),
        // before we try to insert a new DB record
        if (null === $this->findOneByUrl($entity->url)) {
            // Do not exceed URL length limit defined by db schema
            try {
                $this->entityManager->persist($entity);

                if ($flush) {
                    $this->entityManager->flush();
                }
            } catch (\Exception $e) {
                $this->logger->warning('Embed URL exceeds allowed length: {url, length}', ['url' => $entity->url, \strlen($entity->url)]);
            }
        }
    }

    public function remove(Embed $entity, bool $flush = true): void
    {
        $this->entityManager->remove($entity);
        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
