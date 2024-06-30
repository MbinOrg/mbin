<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240625162714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add a unique index on ap_public_url';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX user_ap_public_url_idx ON "user" (ap_public_url)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_ap_public_url_idx');
    }
}
