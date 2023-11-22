<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApActivity;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Service\SettingsManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ApActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApActivity|null findOneByName(string $name)
 * @method ApActivity[]    findAll()
 * @method ApActivity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private SettingsManager $settingsManager)
    {
        parent::__construct($registry, ApActivity::class);
    }

    public function findByObjectId(string $apId): ?array
    {
        $parsed = parse_url($apId);

        if ($parsed['host'] === $this->settingsManager->get('KBIN_DOMAIN')) {
            $exploded = array_filter(explode('/', $parsed['path']));
            $id = end($exploded);

            $type = 'p' === $exploded[3] ? (4 === \count($exploded) ? Post::class : PostComment::class) :
                    ('t' === $exploded[3] ? (4 === \count($exploded) ? Entry::class : EntryComment::class) : null);

            if (null !== $type) {
                return [
                    'id' => $id,
                    'type' => $type,
                ];
            }
        }

        $entryClass = Entry::class;
        $entryCommentClass = EntryComment::class;
        $postClass = Post::class;
        $postCommentClass = PostComment::class;

        $conn = $this->_em->getConnection();
        $sql = '
            (SELECT id, :entryClass AS type FROM entry
            WHERE ap_id = :apId)
            UNION ALL
            (SELECT id, :entryCommentClass AS type FROM entry_comment
            WHERE ap_id = :apId)
            UNION ALL
            (SELECT id, :postClass AS type FROM post
            WHERE ap_id = :apId)
            UNION ALL
            (SELECT id, :postCommentClass AS type FROM post_comment
            WHERE ap_id = :apId)';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('entryClass', $entryClass);
        $stmt->bindValue('entryCommentClass', $entryCommentClass);
        $stmt->bindValue('postClass', $postClass);
        $stmt->bindValue('postCommentClass', $postCommentClass);
        $stmt->bindValue('apId', $apId);
        $results = $stmt->executeQuery()->fetchAllAssociative();

        return \count($results) ? $results[0] : null;
    }
}
