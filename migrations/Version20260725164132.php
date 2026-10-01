<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725164132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add HashtagBlock';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE hashtag_block_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE hashtag_block (id INT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id INT NOT NULL, hashtag_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A7D852AA76ED395 ON hashtag_block (user_id)');
        $this->addSql('CREATE INDEX IDX_A7D852AFB34EF56 ON hashtag_block (hashtag_id)');
        $this->addSql('CREATE UNIQUE INDEX hashtag_block_idx ON hashtag_block (user_id, hashtag_id)');
        $this->addSql('ALTER TABLE hashtag_block ADD CONSTRAINT FK_A7D852AA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE hashtag_block ADD CONSTRAINT FK_A7D852AFB34EF56 FOREIGN KEY (hashtag_id) REFERENCES hashtag (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE hashtag_block_id_seq CASCADE');
        $this->addSql('ALTER TABLE hashtag_block DROP CONSTRAINT FK_A7D852AA76ED395');
        $this->addSql('ALTER TABLE hashtag_block DROP CONSTRAINT FK_A7D852AFB34EF56');
        $this->addSql('DROP TABLE hashtag_block');
    }
}
