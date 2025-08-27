<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Instance;
use App\Entity\Magazine;
use App\Entity\User;
use App\Service\SettingsManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Instance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Instance|null findOneBy(array $criteria, array $orderBy = null)
 * @method Instance[]    findAll()
 * @method Instance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstanceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SettingsManager $settingsManager,
    ) {
        parent::__construct($registry, Instance::class);
    }

    public function getInstanceOfUser(User $user): ?Instance
    {
        return $this->findOneBy(['domain' => $user->apDomain]);
    }

    public function getInstanceOfMagazine(Magazine $magazine): ?Instance
    {
        return $this->findOneBy(['domain' => $magazine->apDomain]);
    }

    public function getAllowedInstances(): array
    {
        $bannedInstances = $this->settingsManager->get('KBIN_BANNED_INSTANCES') ?? [];
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.id');

        if (0 !== \sizeof($bannedInstances)) {
            $qb->where('i.domain NOT IN (:banned)')
                ->setParameter('banned', $bannedInstances);
        }

        return $qb->getQuery()
            ->getResult();
    }

    public function getBannedInstances(): array
    {
        $bannedInstances = $this->settingsManager->get('KBIN_BANNED_INSTANCES') ?? [];
        if (0 === \sizeof($bannedInstances)) {
            return [];
        }

        $bannedInstancesResult = $this->createQueryBuilder('i')
            ->where('i.domain IN (:banned)')
            ->setParameter('banned', $bannedInstances)
            ->orderBy('i.id')
            ->getQuery()
            ->getResult();

        if (\sizeof($bannedInstancesResult) < \sizeof($bannedInstances)) {
            $em = $this->getEntityManager();
            foreach ($bannedInstances as $instance) {
                if (null === $this->findOneBy(['domain' => $instance])) {
                    $i = new Instance($instance);
                    $bannedInstancesResult[] = $i;
                    $em->persist($i);
                }
            }
            $em->flush();
        }

        return $bannedInstancesResult;
    }

    public function getDeadInstances(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.failedDelivers >= :numToDead')
            ->andWhere('i.lastSuccessfulDeliver < :dateBeforeDead OR i.lastSuccessfulDeliver IS NULL')
            ->andWhere('i.lastSuccessfulReceive < :dateBeforeDead OR i.lastSuccessfulReceive IS NULL')
            ->setParameter('numToDead', Instance::NUMBER_OF_FAILED_DELIVERS_UNTIL_DEAD)
            ->setParameter('dateBeforeDead', Instance::getDateBeforeDead())
            ->orderBy('i.id')
            ->getQuery()
            ->getResult();
    }

    public function getOrCreateInstance(string $domain): Instance
    {
        $instance = $this->findOneBy(['domain' => $domain]);
        if (null !== $instance) {
            return $instance;
        }

        $instance = new Instance($domain);
        $this->getEntityManager()->persist($instance);
        $this->getEntityManager()->flush();

        return $instance;
    }
}
