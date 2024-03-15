<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240315110300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This migration adds indexes for user columns which are not lower and will therefore be used much more often';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IF NOT EXISTS user_ap_id_idx ON "user" (ap_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS user_ap_profile_id_idx ON "user" (ap_profile_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS user_email_idx ON "user" (email)');
        $this->addSql('CREATE INDEX IF NOT EXISTS user_username_idx ON "user" (username)');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS user_ap_id_idx');
        $this->addSql('DROP INDEX IF EXISTS user_ap_profile_id_idx');
        $this->addSql('DROP INDEX IF EXISTS user_email_idx');
        $this->addSql('DROP INDEX IF EXISTS user_username_idx');
    }
}