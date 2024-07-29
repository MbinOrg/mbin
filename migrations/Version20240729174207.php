<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240729174207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the posting_restricted_to_mods column to the magazine table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine ADD posting_restricted_to_mods BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine DROP posting_restricted_to_mods');
    }
}
