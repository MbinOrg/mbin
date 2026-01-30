<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251022104152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last key rotation date to user and magazine tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine ADD last_key_rotation_date TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD last_key_rotation_date TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine DROP last_key_rotation_date');
        $this->addSql('ALTER TABLE "user" DROP last_key_rotation_date');
    }
}
