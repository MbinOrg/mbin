<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231019190634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Upgrade User table to store user type (Person, Service, Org...). Default is Person.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD type VARCHAR(80) DEFAULT \'Person\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP type');
    }
}
