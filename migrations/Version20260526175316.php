<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526175316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'adds last_boosted_at with default value to entry, entry_comment, post and post_comment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ADD last_boosted_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT to_timestamp(0)::date');
        $this->addSql('CREATE INDEX entry_last_boosted_at_idx ON entry (last_boosted_at)');
        $this->addSql('ALTER TABLE entry_comment ADD last_boosted_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT to_timestamp(0)::date');
        $this->addSql('CREATE INDEX entry_comment_last_boosted_at_idx ON entry_comment (last_boosted_at)');
        $this->addSql('ALTER TABLE post ADD last_boosted_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT to_timestamp(0)::date');
        $this->addSql('CREATE INDEX post_last_boosted_at_idx ON post (last_boosted_at)');
        $this->addSql('ALTER TABLE post_comment ADD last_boosted_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT to_timestamp(0)::date');
        $this->addSql('CREATE INDEX post_comment_last_boosted_at_idx ON post_comment (last_boosted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX entry_last_boosted_at_idx');
        $this->addSql('ALTER TABLE entry DROP last_boosted_at');
        $this->addSql('DROP INDEX entry_comment_last_boosted_at_idx');
        $this->addSql('ALTER TABLE entry_comment DROP last_boosted_at');
        $this->addSql('DROP INDEX post_last_boosted_at_idx');
        $this->addSql('ALTER TABLE post DROP last_boosted_at');
        $this->addSql('DROP INDEX post_comment_last_boosted_at_idx');
        $this->addSql('ALTER TABLE post_comment DROP last_boosted_at');
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->transactional(function (): void {
            $sqlTpl = 'UPDATE $e SET last_boosted_at = greatest((SELECT $e_vote.created_at FROM $e_vote WHERE $e_vote.$fk = $e.id ORDER BY $e_vote.created_at DESC LIMIT 1), created_at);';
            $this->connection->executeStatement(str_replace('$e', 'entry', str_replace('$fk', 'entry_id', $sqlTpl)));
            $this->connection->executeStatement(str_replace('$e', 'entry_comment', str_replace('$fk', 'comment_id', $sqlTpl)));
            $this->connection->executeStatement(str_replace('$e', 'post', str_replace('$fk', 'post_id', $sqlTpl)));
            $this->connection->executeStatement(str_replace('$e', 'post_comment', str_replace('$fk', 'comment_id', $sqlTpl)));
        });
    }
}
