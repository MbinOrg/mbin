<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240715181419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create a table user_push_subscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE user_push_subscription_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE user_push_subscription (id INT NOT NULL, user_id INT DEFAULT NULL, api_token CHAR(80) DEFAULT NULL, locale VARCHAR(255) DEFAULT NULL, endpoint TEXT NOT NULL, content_encryption_public_key TEXT NOT NULL, device_key UUID DEFAULT NULL, server_auth_key TEXT NOT NULL, notification_types JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AE378BD8A76ED395 ON user_push_subscription (user_id)');
        $this->addSql('ALTER TABLE user_push_subscription ADD api_token CHAR(80) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN user_push_subscription.device_key IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_push_subscription ADD CONSTRAINT FK_AE378BD8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_push_subscription ADD CONSTRAINT FK_AE378BD87BA2F5EB FOREIGN KEY (api_token) REFERENCES oauth2_access_token (identifier) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AE378BD87BA2F5EB ON user_push_subscription (api_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_push_subscription DROP CONSTRAINT FK_AE378BD8A76ED395');
        $this->addSql('ALTER TABLE user_push_subscription DROP CONSTRAINT FK_AE378BD87BA2F5EB');
        $this->addSql('DROP INDEX UNIQ_AE378BD87BA2F5EB');
        $this->addSql('DROP INDEX IDX_AE378BD8A76ED395');
        $this->addSql('DROP INDEX IDX_AE378BD8A76ED395');
        $this->addSql('DROP TABLE user_push_subscription');
        $this->addSql('DROP SEQUENCE user_push_subscription_id_seq');
    }
}
