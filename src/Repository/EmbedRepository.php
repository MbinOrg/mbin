<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Embed;
use App\Utils\LogContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\DriverException;
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
    ) {
        parent::__construct($registry, Embed::class);
    }

    public function add(Embed $entity, bool $flush = true): void
    {
        // Check if embed url does not exist yet (null),
        // before we try to insert a new DB record
        if (null === $this->findOneByUrl($entity->url)) {
            try {
                $this->getEntityManager()->persist($entity);

                if ($flush) {
                    $this->getEntityManager()->flush();
                }
            } catch (\Exception $e) {
                // The database error and embed URL can both expose URL tokens. SQLSTATE identifies the database failure.
                $sqlState = $e instanceof DriverException ? $e->getSQLState() : null;
                $message = '[EmbedRepository::add] Could not save embed from {host} ({url_bytes}-byte URL, {exception_type}';
                if (null !== $sqlState) {
                    $message .= ', SQLSTATE {sqlstate}';
                }
                $this->logger->warning($message.')', [
                    'host' => LogContext::host($entity->url) ?? 'unknown host',
                    'url_bytes' => \strlen($entity->url),
                    'exception_type' => $e::class,
                    'sqlstate' => $sqlState,
                ]);
            }
        }
    }

    public function remove(Embed $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
