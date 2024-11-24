<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241104162655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add application_text and application_status to the user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TYPE enumApplicationStatus AS ENUM (\'Approved\', \'Rejected\', \'Pending\')');
        $this->addSql('ALTER TABLE "user" ADD application_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD application_status enumApplicationStatus DEFAULT \'Approved\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP application_text');
        $this->addSql('ALTER TABLE "user" DROP application_status');
        $this->addSql('DROP TYPE enumApplicationStatus');
    }
}
