<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\BookmarkListDto;
use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\PostCommentDto;
use App\DTO\PostDto;
use App\Entity\BookmarkList;
use App\Entity\Contracts\ContentInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @method BookmarkList|null find($id, $lockMode = null, $lockVersion = null)
 * @method BookmarkList|null findOneBy(array $criteria, array $orderBy = null)
 * @method BookmarkList[]    findAll()
 * @method BookmarkList[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookmarkListRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
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
        $stmt->bindValue('user', $user->getId());
        $stmt->executeStatement();

        $sql = 'UPDATE bookmark_list SET is_default = true WHERE user_id = :user AND id = :id';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('user', $user->getId());
        $stmt->bindValue('id', $list->getId());
        $stmt->executeStatement();
        $this->entityManager->refresh($list);
    }

    public function deleteList(BookmarkList $list): void
    {
        $sql = 'DELETE FROM bookmark_list WHERE id = :id';
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('id', $list->getId());
        $stmt->executeStatement();
    }

    public function editList(User $user, BookmarkList $list, BookmarkListDto $dto): void
    {
        $sql = 'UPDATE bookmark_list SET name = :name WHERE id = :id';
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('id', $list->getId());
        $stmt->bindValue('name', $dto->name);
        $rows = $stmt->executeStatement();

        if ($dto->isDefault) {
            $this->makeListDefault($user, $list);
        } else {
            // makeListDefault already refreshes the entity, so we do not need to do it
            $this->entityManager->refresh($list);
        }
    }

    /**
     * @return BookmarkList[]
     */
    public function findListsBySubject(Entry|EntryDto|EntryComment|EntryCommentDto|Post|PostDto|PostComment|PostCommentDto $content, User $user): array
    {
        $qb = $this->createQueryBuilder('bl')
            ->join('bl.entities', 'b')
            ->where('bl.user = :user')
            ->setParameter('user', $user);

        if ($content instanceof Entry || $content instanceof EntryDto) {
            $qb->andWhere('b.entry = :content');
        } elseif ($content instanceof EntryComment || $content instanceof EntryCommentDto) {
            $qb->andWhere('b.entryComment = :content');
        } elseif ($content instanceof Post || $content instanceof PostDto) {
            $qb->andWhere('b.post = :content');
        } elseif ($content instanceof PostComment || $content instanceof PostCommentDto) {
            $qb->andWhere('b.postComment = :content');
        }
        $qb->setParameter('content', $content->getId());

        return $qb->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    public function getBookmarksOfContentInterface(ContentInterface $content): array
    {
        if ($user = $this->security->getUser()) {
            if ($user instanceof User && (
                $content instanceof Entry
                || $content instanceof EntryDto
                || $content instanceof EntryComment
                || $content instanceof EntryCommentDto
                || $content instanceof Post
                || $content instanceof PostDto
                || $content instanceof PostComment
                || $content instanceof PostCommentDto
            )) {
                return array_map(fn ($list) => $list->name, $this->findListsBySubject($content, $user));
            }
        }

        return [];
    }
}
