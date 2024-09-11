<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\BookmarkListDto;
use App\Entity\BookmarkList;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method BookmarkList|null find($id, $lockMode = null, $lockVersion = null)
 * @method BookmarkList|null findOneBy(array $criteria, array $orderBy = null)
 * @method BookmarkList[]    findAll()
 * @method BookmarkList[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookmarkListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, BookmarkList::class);
    }

    /**
     * @return BookmarkList[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function findOneByUserAndName(User $user, string $name): ?BookmarkList
    {
        return $this->findOneBy(['user' => $user, 'name' => $name]);
    }

    public function findOneByUserDefault(User $user): BookmarkList
    {
        $list = $this->findOneBy(['user' => $user, 'isDefault' => true]);
        if (null === $list) {
            $list = new BookmarkList($user, 'Default', true);
            $this->entityManager->persist($list);
            $this->entityManager->flush();
        }

        return $list;
    }

    public function makeListDefault(User $user, BookmarkList $list): void
    {
        $sql = 'UPDATE bookmark_list SET is_default = false WHERE user_id = :user';
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement(['user' => $user->getId()]);

        $sql = 'UPDATE bookmark_list SET is_default = true WHERE user_id = :user AND id = :id';
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement(['user' => $user->getId(), 'id' => $list->getId()]);
    }

    public function deleteList(BookmarkList $list): void
    {
        $sql = 'DELETE FROM bookmark_list WHERE id = :id';
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement(['id' => $list->getId()]);
    }

    public function editList(User $user, BookmarkList $list, BookmarkListDto $dto): void
    {
        $sql = 'UPDATE bookmark_list SET name = :name WHERE id = :id';
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement(['id' => $list->getId(), 'name' => $dto->name]);

        if ($dto->isDefault) {
            $this->makeListDefault($user, $list);
        }
    }
}
