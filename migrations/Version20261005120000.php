<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store IP screening results for pending registration applications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD registration_screening JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP registration_screening');
    }
}
