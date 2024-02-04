<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240204141515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS citext');
        $this->addSql('ALTER TABLE "user" ALTER COLUMN username TYPE citext');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ALTER COLUMN username TYPE VARCHAR(35)');
        $this->addSql('DROP EXTENSION citext');
    }
}
