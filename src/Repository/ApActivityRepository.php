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
    private const TYPE_MAP = [
        'p' => [Post::class, PostComment::class],
        't' => [Entry::class, EntryComment::class],
    ];

    public function __construct(ManagerRegistry $registry, private SettingsManager $settingsManager)
    {
        parent::__construct($registry, ApActivity::class);
    }

    public function findByObjectId(string $apId): ?array
    {
        $exploded = [];

        $parsed = parse_url($apId);
        if ($parsed['host'] === $this->settingsManager->get('KBIN_DOMAIN') && isset(self::TYPE_MAP[$parsed['path'][3]])) {
            $exploded = array_filter(explode('/', $parsed['path']));
            $id = end($exploded);
            $type = self::TYPE_MAP[$exploded[3]][\count($exploded)];

            return ['id' => $id, 'type' => $type];
        }

        $conn = $this->_em->getConnection();
        $sql = '
            SELECT id, :type AS type
            FROM (
                SELECT id, :entryClass AS type FROM entry
                UNION
                SELECT id, :entryCommentClass FROM entry_comment
                UNION
                SELECT id, :postClass FROM post
                UNION
                SELECT id, :postCommentClass FROM post_comment
            ) AS combined
            WHERE ap_id = :apId
            LIMIT 1';
        
        $stmt = $conn->prepare($sql)->executeQuery([
            'type' => self::TYPE_MAP[$parsed['path'][3]][\count($exploded)],
            'entryClass' => Entry::class,
            'entryCommentClass' => EntryComment::class,
            'postClass' => Post::class,
            'postCommentClass' => PostComment::class,
            'apId' => $apId,
        ]);

        return $stmt->fetchAllAssociative() ?: null;
    }
}
