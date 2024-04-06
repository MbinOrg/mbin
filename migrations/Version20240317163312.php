<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240317163312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This migration changes the report.reporting_id foreign key to cascade delete instead of cascading to null (which is not possible, because the column is not nullable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP CONSTRAINT IF EXISTS FK_C42F778427EE0E60');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F778427EE0E60 FOREIGN KEY (reporting_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP CONSTRAINT IF EXISTS FK_C42F778427EE0E60');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT fk_c42f778427ee0e60 FOREIGN KEY (reporting_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
