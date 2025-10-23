<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250907112001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add old public and private key to the user and magazine tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine ADD old_private_key TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE magazine ADD old_public_key TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD old_private_key TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD old_public_key TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine DROP old_private_key');
        $this->addSql('ALTER TABLE magazine DROP old_public_key');
        $this->addSql('ALTER TABLE "user" DROP old_private_key');
        $this->addSql('ALTER TABLE "user" DROP old_public_key');
    }
}
