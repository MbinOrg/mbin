<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240718232800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the push keys to the site table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site ADD COLUMN push_private_key text DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD COLUMN push_public_key text DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site DROP COLUMN push_private_key');
        $this->addSql('ALTER TABLE site DROP COLUMN push_public_key');
    }
}
