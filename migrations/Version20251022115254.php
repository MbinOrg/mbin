<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251022115254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add magazine banner';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine ADD banner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE magazine ADD CONSTRAINT FK_378C2FE4684EC833 FOREIGN KEY (banner_id) REFERENCES image (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_378C2FE4684EC833 ON magazine (banner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine DROP CONSTRAINT FK_378C2FE4684EC833');
        $this->addSql('DROP INDEX IDX_378C2FE4684EC833');
        $this->addSql('ALTER TABLE magazine DROP banner_id');
    }
}
