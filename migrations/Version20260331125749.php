<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331125749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        /* User and Magazine both use ActivityPubActorTrait but User has a nullable title, so this migration **must** always be removed.
         * $this->addSql('ALTER TABLE "user" ALTER title SET NOT NULL');
         */

        /* junk tsvector migrations
         * $this->addSql('ALTER TABLE entry ALTER title_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE entry ALTER body_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE entry_comment ALTER body_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE magazine ALTER name_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE magazine ALTER title_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE magazine ALTER description_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE post ALTER body_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE post_comment ALTER body_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE "user" ALTER username_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE "user" ALTER about_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE "user" ALTER title_ts DROP DEFAULT');
         */
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entry ALTER title_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (title)::text)\'');
        $this->addSql('ALTER TABLE entry ALTER body_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, body)\'');
        $this->addSql('ALTER TABLE entry_comment ALTER body_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, body)\'');
        $this->addSql('ALTER TABLE magazine ALTER name_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (name)::text)\'');
        $this->addSql('ALTER TABLE magazine ALTER title_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (title)::text)\'');
        $this->addSql('ALTER TABLE magazine ALTER description_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, description)\'');
        $this->addSql('ALTER TABLE post ALTER body_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, body)\'');
        $this->addSql('ALTER TABLE post_comment ALTER body_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, body)\'');
        $this->addSql('ALTER TABLE "user" ALTER username_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (username)::text)\'');
        $this->addSql('ALTER TABLE "user" ALTER title_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (title)::text)\'');
        $this->addSql('ALTER TABLE "user" ALTER about_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, about)\'');
        $this->addSql('ALTER TABLE "user" ALTER title DROP NOT NULL');
    }
}
