<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224224633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add displayname to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD displayname VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD displayname_ts tsvector GENERATED ALWAYS AS (to_tsvector(\'english\', displayname)) STORED');
        $this->addSql('CREATE INDEX user_displayname_ts ON "user" USING GIN (displayname_ts)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_displayname_ts');
        $this->addSql('ALTER TABLE "user" DROP displayname_ts');
        $this->addSql('ALTER TABLE "user" DROP displayname');
    }
}
