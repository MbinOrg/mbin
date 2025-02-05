<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250203232039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This migration does a little bit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT IF EXISTS FK_B0559860A76ED395');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT IF EXISTS FK_B0559860A76ED395');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
