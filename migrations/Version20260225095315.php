<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225095315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add magazine log columns for the purged classes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine_log ADD author_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE magazine_log ADD title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE magazine_log ADD CONSTRAINT FK_87D3D4C5F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_87D3D4C5F675F31B ON magazine_log (author_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine_log DROP CONSTRAINT FK_87D3D4C5F675F31B');
        $this->addSql('DROP INDEX IDX_87D3D4C5F675F31B');
        $this->addSql('ALTER TABLE magazine_log DROP author_id');
        $this->addSql('ALTER TABLE magazine_log DROP title');
        $this->addSql('ALTER TABLE magazine_log DROP short_body');
    }
}
