<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240628142700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'remove the unique index on ap_public_url';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_ap_public_url_idx');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX user_ap_public_url_idx ON "user" (ap_public_url)');
    }
}
