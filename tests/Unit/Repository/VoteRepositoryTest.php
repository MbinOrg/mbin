<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\VoteRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VoteRepositoryTest extends TestCase
{
    #[DataProvider('countProvider')]
    public function testCount(?\DateTimeImmutable $date, bool $withFederated): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(5))->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());

        $result = $this->createMock(DriverResult::class);
        $result->expects(self::exactly(5))->method('fetchAllAssociative')->willReturn([['cnt' => 2]]);

        $driverStatement = $this->createMock(DriverStatement::class);
        $driverStatement->expects(self::exactly(null === $date ? 0 : 5))
            ->method('bindValue')
            ->with(':date', $date?->format('Y-m-d H:i:s'), ParameterType::STRING);
        $driverStatement->expects(self::exactly(5))->method('execute')->willReturn($result);

        $tables = ['entry_vote', 'entry_comment_vote', 'post_vote', 'post_comment_vote', 'favourite'];
        $connection->expects(self::exactly(5))->method('prepare')
            ->willReturnCallback(function (string $sql) use ($connection, $driverStatement, $date, $withFederated, &$tables): Statement {
                self::assertStringContainsString('FROM '.array_shift($tables).' e ', $sql);
                self::assertStringContainsString('WHERE u.is_deleted = false', $sql);
                self::assertSame(null !== $date, str_contains($sql, 'e.created_at > :date'));
                self::assertSame(!$withFederated, str_contains($sql, 'u.ap_id IS NULL'));

                // Exercise DBAL's real type conversion before passing values to the driver.
                return new Statement($connection, $driverStatement, $sql);
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(5))->method('getConnection')->willReturn($connection);

        self::assertSame(10, (new VoteRepository($entityManager))->count($date, $withFederated));
    }

    public static function countProvider(): array
    {
        $date = new \DateTimeImmutable('2026-10-01 12:34:56');

        return [
            'dated local' => [$date, false],
            'dated federated' => [$date, true],
            'all time local' => [null, false],
            'all time federated' => [null, true],
        ];
    }
}
