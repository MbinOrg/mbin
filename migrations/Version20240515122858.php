<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240515122858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the report field for notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD report_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA4BD2A4C0 FOREIGN KEY (report_id) REFERENCES report (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_BF5476CA4BD2A4C0 ON notification (report_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA4BD2A4C0');
        $this->addSql('DROP INDEX IDX_BF5476CA4BD2A4C0');
        $this->addSql('ALTER TABLE notification DROP report_id');
    }
}
