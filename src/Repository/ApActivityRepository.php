<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApActivity;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Service\SettingsManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use JetBrains\PhpStorm\ArrayShape;

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

    #[ArrayShape([
        'id' => 'int',
        'type' => 'string',
    ])]
    public function findByObjectId(string $apId): ?array
    {
        $local = $this->findLocalByApId($apId);
        if ($local) {
            return $local;
        }

        $conn = $this->_em->getConnection();
        $tables = [
            ['table' => 'entry', 'class' => Entry::class],
            ['table' => 'entry_comment', 'class' => EntryComment::class],
            ['table' => 'post', 'class' => Post::class],
            ['table' => 'post_comment', 'class' => PostComment::class],
            ['table' => 'message', 'class' => Message::class],
        ];
        foreach ($tables as $table) {
            $t = $table['table'];
            $sql = "SELECT id FROM $t WHERE ap_id = :apId";
            try {
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('apId', $apId);
                $results = $stmt->executeQuery()->fetchAllAssociative();

                if (1 === \sizeof($results) && \array_key_exists('id', $results[0])) {
                    return [
                        'id' => $results[0]['id'],
                        'type' => $table['class'],
                    ];
                }
            } catch (Exception) {
            }
        }

        return null;
    }

    #[ArrayShape([
        'id' => 'int',
        'type' => 'string',
    ])]
    public function findLocalByApId(string $apId): ?array
    {
        $parsed = parse_url($apId);
        if ($parsed['host'] === $this->settingsManager->get('KBIN_DOMAIN')) {
            $exploded = array_filter(explode('/', $parsed['path']));
            $id = \intval(end($exploded));
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

            if ('message' === $exploded[3]) {
                if (4 === \count($exploded)) {
                    return [
                        'id' => $id,
                        'type' => Message::class,
                    ];
                }
            }
        }

        return null;
    }
}
