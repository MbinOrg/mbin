<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20231130203400 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report ADD uuid VARCHAR(255) NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_REPORT_UUID ON report (uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP uuid');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_REPORT_UUID ON report (uuid)');
    }
}
