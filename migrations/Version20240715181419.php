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
        $this->addSql('CREATE TABLE user_push_subscription (id INT NOT NULL, user_id INT DEFAULT NULL, user_consent INT DEFAULT NULL, locale VARCHAR(255) DEFAULT NULL, endpoint TEXT NOT NULL, content_encryption_public_key TEXT NOT NULL, device_key UUID DEFAULT NULL, server_auth_key TEXT NOT NULL, notification_types JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AE378BD8A76ED395 ON user_push_subscription (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AE378BD83B1F161A ON user_push_subscription (user_consent)');
        $this->addSql('COMMENT ON COLUMN user_push_subscription.device_key IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_push_subscription ADD CONSTRAINT FK_AE378BD8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_push_subscription ADD CONSTRAINT FK_AE378BD83B1F161A FOREIGN KEY (user_consent) REFERENCES oauth2_user_consent (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE oauth2_user_consent ADD push_subscription INT DEFAULT NULL');
        $this->addSql('ALTER TABLE oauth2_user_consent ADD CONSTRAINT FK_C8F05D01562830F3 FOREIGN KEY (push_subscription) REFERENCES user_push_subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C8F05D01562830F3 ON oauth2_user_consent (push_subscription)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth2_user_consent DROP CONSTRAINT FK_C8F05D01562830F3');
        $this->addSql('ALTER TABLE user_push_subscription DROP CONSTRAINT FK_AE378BD8A76ED395');
        $this->addSql('ALTER TABLE user_push_subscription DROP CONSTRAINT FK_AE378BD83B1F161A');
        $this->addSql('DROP TABLE user_push_subscription');
        $this->addSql('DROP INDEX UNIQ_C8F05D01562830F3');
        $this->addSql('ALTER TABLE oauth2_user_consent DROP push_subscription');
        $this->addSql('DROP SEQUENCE user_push_subscription_id_seq');
    }
}
