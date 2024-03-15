<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240315124130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This migration adds a unique index for the ap_profile_id and renames the other cryptically named indexes to understandable ones';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS user_ap_profile_id_idx ON "user" (ap_profile_id)');
        $this->addSql('ALTER INDEX IF EXISTS uniq_8d93d649e7927c74 RENAME TO user_email_idx');
        $this->addSql('ALTER INDEX IF EXISTS uniq_8d93d649f85e0677 RENAME TO user_username_idx');
        $this->addSql('ALTER INDEX IF EXISTS uniq_8d93d649904f155e RENAME TO user_ap_id_idx');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS user_ap_profile_id_idx');
        $this->addSql('ALTER INDEX IF EXISTS user_username_idx RENAME TO uniq_8d93d649f85e0677');
        $this->addSql('ALTER INDEX IF EXISTS user_email_idx RENAME TO uniq_8d93d649e7927c74');
        $this->addSql('ALTER INDEX IF EXISTS user_ap_id_idx RENAME TO uniq_8d93d649904f155e');
    }
}
