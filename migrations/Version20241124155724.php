<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241124155724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new_user_id to notification table and notify_on_user_signup to "user" table for the `NewSignupNotification`';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD new_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA7C2D807B FOREIGN KEY (new_user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_BF5476CA7C2D807B ON notification (new_user_id)');
        $this->addSql('ALTER TABLE "user" ADD notify_on_user_signup BOOLEAN DEFAULT TRUE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA7C2D807B');
        $this->addSql('DROP INDEX IDX_BF5476CA7C2D807B');
        $this->addSql('ALTER TABLE notification DROP new_user_id');
        $this->addSql('ALTER TABLE "user" DROP notify_on_user_signup');
    }
}
