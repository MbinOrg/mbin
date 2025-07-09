<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250706115844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ts vectors for user and magazine columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine ADD name_ts tsvector GENERATED ALWAYS AS (to_tsvector(\'english\', name)) STORED');
        $this->addSql('ALTER TABLE magazine ADD title_ts tsvector GENERATED ALWAYS AS (to_tsvector(\'english\', title)) STORED');
        $this->addSql('ALTER TABLE magazine ADD description_ts tsvector GENERATED ALWAYS AS (to_tsvector(\'english\', description)) STORED');
        $this->addSql('CREATE INDEX magazine_name_ts ON magazine USING GIN (name_ts)');
        $this->addSql('CREATE INDEX magazine_title_ts ON magazine USING GIN (title_ts)');
        $this->addSql('CREATE INDEX magazine_description_ts ON magazine USING GIN (description_ts)');

        $this->addSql('ALTER TABLE "user" ADD username_ts tsvector GENERATED ALWAYS AS (to_tsvector(\'english\', username)) STORED');
        $this->addSql('ALTER TABLE "user" ADD about_ts tsvector GENERATED ALWAYS AS (to_tsvector(\'english\', about)) STORED');
        $this->addSql('CREATE INDEX user_username_ts ON "user" USING GIN (username_ts)');
        $this->addSql('CREATE INDEX user_about_ts ON "user" USING GIN (about_ts)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_username_ts');
        $this->addSql('DROP INDEX user_about_ts');
        $this->addSql('ALTER TABLE "user" DROP username_ts');
        $this->addSql('ALTER TABLE "user" DROP about_ts');

        $this->addSql('DROP INDEX magazine_name_ts');
        $this->addSql('DROP INDEX magazine_title_ts');
        $this->addSql('DROP INDEX magazine_description_ts');
        $this->addSql('ALTER TABLE magazine DROP name_ts');
        $this->addSql('ALTER TABLE magazine DROP title_ts');
        $this->addSql('ALTER TABLE magazine DROP description_ts');
    }
}
