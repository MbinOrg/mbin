<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260129214946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth2_client_access ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE oauth2_user_consent ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE oauth2_user_consent ALTER expires_at TYPE TIMESTAMP(0) WITH TIME ZONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth2_client_access ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE oauth2_user_consent ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE oauth2_user_consent ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
    }
}
