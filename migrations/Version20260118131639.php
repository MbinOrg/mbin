<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260118131639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the unique index on "user".ap_public_url if it does not exist';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS user_ap_public_url_idx ON "user" (ap_public_url)');
    }

    public function down(Schema $schema): void
    {
    }
}
