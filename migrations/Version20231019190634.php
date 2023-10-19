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
        $this->addSql('CREATE TYPE user_type AS ENUM (\'Person\', \'Service\', \'Organization\', \'Application\')');
        $this->addSql('ALTER TABLE "user" ADD COLUMN "type" user_type NOT NULL DEFAULT \'Person\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN "type"');
        $this->addSql('DROP TYPE IF EXISTS user_type');
    }
}
