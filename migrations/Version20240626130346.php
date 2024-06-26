<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240626130346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD edited_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN message.edited_at IS \'(DC2Type:datetimetz_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP edited_at');
    }
}
