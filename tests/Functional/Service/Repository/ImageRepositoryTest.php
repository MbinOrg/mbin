<?php
declare(strict_types=1);

namespace App\Tests\Functional\Service\Repository;

use App\Tests\WebTestCase;

class ImageRepositoryTest extends WebTestCase
{
    public function testFindMultipleBySha256AndLock(): void
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');
        $this->createImage('test3.png');

        $this->entityManager->wrapInTransaction(function () use ($img1, $img2) {
            $result = $this->imageRepository->findMultipleBySha256AndLock([
                $img1->sha256,
                $img2->sha256,
                hex2bin('0000000000000000000000000000000000000000000000000000000000000000'),
            ]);

            self::assertCount(2, $result);
            self::assertContains($img1, $result);
            self::assertContains($img2, $result);

            $locks = $this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM pg_locks l JOIN pg_stat_all_tables t ON l.relation = t.relid WHERE t.relname = \'image\' AND l.granted = TRUE AND l.mode = \'RowShareLock\'');
            self::assertCount(1, $locks);
        });
    }
}
