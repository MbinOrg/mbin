<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250204152300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'remove confusing comment on notification_settings.notification_status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN notification_settings.notification_status IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN notification_settings.notification_status IS \'(DC2Type:EnumNotificationStatus)\'');
    }
}
