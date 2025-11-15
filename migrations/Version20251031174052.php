<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251031174052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_locked column to post and entry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ADD is_locked BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE post ADD is_locked BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post DROP is_locked');
        $this->addSql('ALTER TABLE entry DROP is_locked');
    }
}
