<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231125005121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new muted column on user table + index';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD muted BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX user_muted_idx ON "user" (muted)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX user_muted_idx');
        $this->addSql('ALTER TABLE "user" DROP muted');
    }
}
