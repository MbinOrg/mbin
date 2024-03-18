<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240313222328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This migration removes all the duplicates from the favourite table and creates 4 unique indexes for each combination of user_id with [entry|entry_comment|post|post_comment]_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM favourite a USING favourite b WHERE a.id > b.id AND a.entry_id = b.entry_id AND a.user_id = b.user_id');
        $this->addSql('DELETE FROM favourite a USING favourite b WHERE a.id > b.id AND a.entry_comment_id = b.entry_comment_id AND a.user_id = b.user_id');
        $this->addSql('DELETE FROM favourite a USING favourite b WHERE a.id > b.id AND a.post_id = b.post_id AND a.user_id = b.user_id');
        $this->addSql('DELETE FROM favourite a USING favourite b WHERE a.id > b.id AND a.post_comment_id = b.post_comment_id AND a.user_id = b.user_id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS favourite_user_entry_unique_idx ON favourite (entry_id, user_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS favourite_user_entry_comment_unique_idx ON favourite (entry_comment_id, user_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS favourite_user_post_unique_idx ON favourite (post_id, user_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS favourite_user_post_comment_unique_idx ON favourite (post_comment_id, user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS favourite_user_entry_unique_idx');
        $this->addSql('DROP INDEX IF EXISTS favourite_user_entry_comment_unique_idx');
        $this->addSql('DROP INDEX IF EXISTS favourite_user_post_unique_idx');
        $this->addSql('DROP INDEX IF EXISTS favourite_user_post_comment_unique_idx');
    }
}
