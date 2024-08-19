<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240815162107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cascade delete to report.considered_by_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784607E02EB');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784607E02EB FOREIGN KEY (considered_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP CONSTRAINT fk_c42f7784607e02eb');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT fk_c42f7784607e02eb FOREIGN KEY (considered_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
