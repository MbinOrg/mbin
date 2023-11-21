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
            if ('p' === $exploded[3]) {
                if (4 === \count($exploded)) {
                    return [
                        'id' => $id,
                        'type' => Post::class,
                    ];
                } else {
                    return [
                        'id' => $id,
                        'type' => PostComment::class,
                    ];
                }
            }

            if ('t' === $exploded[3]) {
                if (4 === \count($exploded)) {
                    return [
                        'id' => $id,
                        'type' => Entry::class,
                    ];
                } else {
                    return [
                        'id' => $id,
                        'type' => EntryComment::class,
                    ];
                }
            }
        }

        $entryClass = Entry::class;
        $entryCommentClass = EntryComment::class;
        $postClass = Post::class;
        $postCommentClass = PostComment::class;

        $conn = $this->_em->getConnection();
        $sql = '
        (SELECT
            id,
            CASE
                WHEN table_name = \'entry\' THEN :entryClass
                WHEN table_name = \'entry_comment\' THEN :entryCommentClass
                WHEN table_name = \'post\' THEN :postClass
                WHEN table_name = \'post_comment\' THEN :postCommentClass
            END AS type
        FROM
            (
                SELECT id, \'entry\' AS table_name FROM entry WHERE ap_id = :apId
                UNION
                SELECT id, \'entry_comment\' FROM entry_comment WHERE ap_id = :apId
                UNION
                SELECT id, \'post\' FROM post WHERE ap_id = :apId
                UNION
                SELECT id, \'post_comment\' FROM post_comment WHERE ap_id = :apId
            ) AS combined_result)';

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
