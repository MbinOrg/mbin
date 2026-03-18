<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315190023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'adds show_boosts_of_following';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD show_boosts_of_following BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP show_boosts_of_following');
    }
}
