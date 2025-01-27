<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241125210454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the notification_settings table for customized notification settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TYPE enumNotificationStatus AS ENUM(\'Default\', \'Muted\', \'Loud\')');
        $this->addSql('CREATE SEQUENCE notification_settings_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE notification_settings (id INT NOT NULL, user_id INT NOT NULL, entry_id INT DEFAULT NULL, post_id INT DEFAULT NULL, magazine_id INT DEFAULT NULL, target_user_id INT DEFAULT NULL, notification_status enumNotificationStatus DEFAULT \'Default\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B0559860A76ED395 ON notification_settings (user_id)');
        $this->addSql('CREATE INDEX IDX_B0559860BA364942 ON notification_settings (entry_id)');
        $this->addSql('CREATE INDEX IDX_B05598604B89032C ON notification_settings (post_id)');
        $this->addSql('CREATE INDEX IDX_B05598603EB84A1D ON notification_settings (magazine_id)');
        $this->addSql('CREATE INDEX IDX_B05598606C066AFE ON notification_settings (target_user_id)');
        $this->addSql('CREATE UNIQUE INDEX notification_settings_user_target ON notification_settings (user_id, entry_id, post_id, magazine_id, target_user_id)');
        $this->addSql('COMMENT ON COLUMN notification_settings.notification_status IS \'(DC2Type:EnumNotificationStatus)\'');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860BA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598604B89032C FOREIGN KEY (post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598603EB84A1D FOREIGN KEY (magazine_id) REFERENCES magazine (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598606C066AFE FOREIGN KEY (target_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE notification_settings_id_seq CASCADE');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B0559860A76ED395');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B0559860BA364942');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598604B89032C');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598603EB84A1D');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598606C066AFE');
        $this->addSql('DROP TABLE notification_settings');
        $this->addSql('DROP TYPE enumNotificationStatus');
    }
}
