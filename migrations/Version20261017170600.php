<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017170600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set the max length of oauth2_client identifier and secret to 1024';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth2_client ALTER COLUMN identifier TYPE varchar(1024)');
        $this->addSql('ALTER TABLE oauth2_client ALTER COLUMN secret TYPE varchar(1024)');
        $this->addSql('ALTER TABLE oauth2_user_consent ALTER COLUMN client_identifier TYPE varchar(1024)');
        $this->addSql('ALTER TABLE oauth2_client_access ALTER COLUMN client_id TYPE varchar(1024)');
        $this->addSql('ALTER TABLE oauth2_authorization_code ALTER COLUMN client TYPE varchar(1024)');
        $this->addSql('ALTER TABLE oauth2_access_token ALTER COLUMN client TYPE varchar(1024)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth2_client ALTER COLUMN identifier TYPE varchar(32)');
        $this->addSql('ALTER TABLE oauth2_client ALTER COLUMN secret TYPE varchar(128)');
        $this->addSql('ALTER TABLE oauth2_user_consent ALTER COLUMN client_identifier TYPE varchar(32)');
        $this->addSql('ALTER TABLE oauth2_client_access ALTER COLUMN client_id TYPE varchar(32)');
        $this->addSql('ALTER TABLE oauth2_authorization_code ALTER COLUMN client TYPE varchar(32)');
        $this->addSql('ALTER TABLE oauth2_access_token ALTER COLUMN client TYPE varchar(32)');
    }
}
