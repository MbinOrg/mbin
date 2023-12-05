<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231112133420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add column "added_by_user_id" to moderator table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderator ADD added_by_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE moderator ADD CONSTRAINT FK_6A30B268CA792C6B FOREIGN KEY (added_by_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6A30B268CA792C6B ON moderator (added_by_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderator DROP CONSTRAINT FK_6A30B268CA792C6B');
        $this->addSql('DROP INDEX IDX_6A30B268CA792C6B');
        $this->addSql('ALTER TABLE moderator DROP added_by_user_id');
    }
}
