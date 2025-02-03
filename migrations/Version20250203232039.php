<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250203232039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing cascade delete to the constraints of the notification_settings table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B0559860A76ED395');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B0559860BA364942');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598604B89032C');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598603EB84A1D');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598606C066AFE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860BA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598604B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598603EB84A1D FOREIGN KEY (magazine_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598606C066AFE FOREIGN KEY (target_user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B0559860A76ED395');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B0559860BA364942');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598604B89032C');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598603EB84A1D');
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B05598606C066AFE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860BA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598604B89032C FOREIGN KEY (post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598603EB84A1D FOREIGN KEY (magazine_id) REFERENCES magazine (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B05598606C066AFE FOREIGN KEY (target_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
