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
        return 'Add title to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD title_ts tsvector GENERATED ALWAYS AS (to_tsvector(\'english\', title)) STORED');
        $this->addSql('CREATE INDEX user_title_ts ON "user" USING GIN (title_ts)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_title_ts');
        $this->addSql('ALTER TABLE "user" DROP title_ts');
        $this->addSql('ALTER TABLE "user" DROP title');
    }
}
