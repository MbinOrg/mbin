<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240603190838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add a uuid and an ap_id to the message table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD uuid UUID NOT NULL DEFAULT gen_random_uuid()');
        $this->addSql('ALTER TABLE message ADD ap_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN message.uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6BD307FD17F50A6 ON message (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6BD307F904F155E ON message (ap_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_B6BD307FD17F50A6');
        $this->addSql('DROP INDEX UNIQ_B6BD307F904F155E');
        $this->addSql('ALTER TABLE message DROP uuid');
        $this->addSql('ALTER TABLE message DROP ap_id');
    }
}
