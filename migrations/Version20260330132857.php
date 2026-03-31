<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330132857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial user_filter_list table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE user_filter_list_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE user_filter_list (id INT NOT NULL, name VARCHAR(255) NOT NULL, expiration_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, feeds BOOLEAN NOT NULL, profile BOOLEAN NOT NULL, comments BOOLEAN NOT NULL, words JSONB NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_85E956F4A76ED395 ON user_filter_list (user_id)');
        $this->addSql('ALTER TABLE user_filter_list ADD CONSTRAINT FK_85E956F4A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE user_filter_list_id_seq CASCADE');
        $this->addSql('ALTER TABLE user_filter_list DROP CONSTRAINT FK_85E956F4A76ED395');
        $this->addSql('DROP TABLE user_filter_list');
    }
}
