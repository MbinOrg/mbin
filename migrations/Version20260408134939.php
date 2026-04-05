<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408134939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial poll table and relations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE poll_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE poll_choice_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE poll (id INT NOT NULL, multiple_choice BOOLEAN NOT NULL, voter_count INT DEFAULT 0 NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_remote BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, sent_notifications BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE poll_choice (id INT NOT NULL, name VARCHAR(255) NOT NULL, vote_count INT NOT NULL, poll_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2DAE19C93C947C0F ON poll_choice (poll_id)');
        $this->addSql('CREATE TABLE poll_vote (uuid UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ap_id VARCHAR(255) DEFAULT NULL, voter_id INT NOT NULL, choice_id INT NOT NULL, poll_id INT NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ED568EBE904F155E ON poll_vote (ap_id)');
        $this->addSql('CREATE INDEX IDX_ED568EBEEBB4B8AD ON poll_vote (voter_id)');
        $this->addSql('CREATE INDEX IDX_ED568EBE998666D1 ON poll_vote (choice_id)');
        $this->addSql('CREATE INDEX IDX_ED568EBE3C947C0F ON poll_vote (poll_id)');
        $this->addSql('ALTER TABLE poll_choice ADD CONSTRAINT FK_2DAE19C93C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_ED568EBEEBB4B8AD FOREIGN KEY (voter_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_ED568EBE998666D1 FOREIGN KEY (choice_id) REFERENCES poll_choice (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_ED568EBE3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE activity ADD object_poll_vote_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A69F3DEA4 FOREIGN KEY (object_poll_vote_id) REFERENCES poll_vote (uuid) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_AC74095A69F3DEA4 ON activity (object_poll_vote_id)');
        $this->addSql('ALTER TABLE entry ADD poll_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D703C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2B219D703C947C0F ON entry (poll_id)');
        $this->addSql('ALTER TABLE entry_comment ADD poll_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry_comment ADD CONSTRAINT FK_B892FDFB3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B892FDFB3C947C0F ON entry_comment (poll_id)');
        $this->addSql('ALTER TABLE post ADD poll_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8A6C8D3C947C0F ON post (poll_id)');
        $this->addSql('ALTER TABLE post_comment ADD poll_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55F3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A99CE55F3C947C0F ON post_comment (poll_id)');
        $this->addSql('ALTER TABLE notification ADD poll_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_BF5476CA3C947C0F ON notification (poll_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE poll_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE poll_choice_id_seq CASCADE');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A69F3DEA4');
        $this->addSql('DROP INDEX IDX_AC74095A69F3DEA4');
        $this->addSql('ALTER TABLE activity DROP object_poll_vote_id');
        $this->addSql('ALTER TABLE entry DROP CONSTRAINT FK_2B219D703C947C0F');
        $this->addSql('DROP INDEX UNIQ_2B219D703C947C0F');
        $this->addSql('ALTER TABLE entry DROP poll_id');
        $this->addSql('ALTER TABLE entry_comment DROP CONSTRAINT FK_B892FDFB3C947C0F');
        $this->addSql('DROP INDEX UNIQ_B892FDFB3C947C0F');
        $this->addSql('ALTER TABLE entry_comment DROP poll_id');
        $this->addSql('ALTER TABLE post DROP CONSTRAINT FK_5A8A6C8D3C947C0F');
        $this->addSql('DROP INDEX UNIQ_5A8A6C8D3C947C0F');
        $this->addSql('ALTER TABLE post DROP poll_id');
        $this->addSql('ALTER TABLE post_comment DROP CONSTRAINT FK_A99CE55F3C947C0F');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA3C947C0F');
        $this->addSql('DROP INDEX IDX_BF5476CA3C947C0F');
        $this->addSql('ALTER TABLE notification DROP poll_id');
        $this->addSql('DROP INDEX UNIQ_A99CE55F3C947C0F');
        $this->addSql('ALTER TABLE post_comment DROP poll_id');
        $this->addSql('ALTER TABLE poll_choice DROP CONSTRAINT FK_2DAE19C93C947C0F');
        $this->addSql('ALTER TABLE poll_vote DROP CONSTRAINT FK_ED568EBEEBB4B8AD');
        $this->addSql('ALTER TABLE poll_vote DROP CONSTRAINT FK_ED568EBE998666D1');
        $this->addSql('ALTER TABLE poll_vote DROP CONSTRAINT FK_ED568EBE3C947C0F');
        $this->addSql('DROP TABLE poll');
        $this->addSql('DROP TABLE poll_choice');
        $this->addSql('DROP TABLE poll_vote');
    }
}
