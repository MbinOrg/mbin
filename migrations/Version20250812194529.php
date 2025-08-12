<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250812194529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_at to the activity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL default CURRENT_TIMESTAMP(0)');
        $this->addSql('COMMENT ON COLUMN activity.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE activity ALTER COLUMN created_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP created_at');
    }
}
