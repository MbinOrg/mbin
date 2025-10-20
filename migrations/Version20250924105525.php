<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250924105525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add column direct_message_setting to "user"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TYPE enumDirectMessageSettings AS ENUM(\'everyone\', \'followers_only\', \'nobody\')');
        $this->addSql('ALTER TABLE "user" ADD direct_message_setting enumDirectMessageSettings DEFAULT \'everyone\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP direct_message_setting');
        $this->addSql('DROP TYPE enumDirectMessageSettings');
    }
}
