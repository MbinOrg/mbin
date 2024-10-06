<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240831151328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This adds the bookmark and bookmark list tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE bookmark_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE bookmark_list_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE bookmark (id INT NOT NULL, list_id INT NOT NULL, user_id INT NOT NULL, entry_id INT DEFAULT NULL, entry_comment_id INT DEFAULT NULL, post_id INT DEFAULT NULL, post_comment_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DA62921D3DAE168B ON bookmark (list_id)');
        $this->addSql('CREATE INDEX IDX_DA62921DA76ED395 ON bookmark (user_id)');
        $this->addSql('CREATE INDEX IDX_DA62921DBA364942 ON bookmark (entry_id)');
        $this->addSql('CREATE INDEX IDX_DA62921D60C33421 ON bookmark (entry_comment_id)');
        $this->addSql('CREATE INDEX IDX_DA62921D4B89032C ON bookmark (post_id)');
        $this->addSql('CREATE INDEX IDX_DA62921DDB1174D2 ON bookmark (post_comment_id)');
        $this->addSql('CREATE UNIQUE INDEX bookmark_list_entry_entryComment_post_postComment_idx ON bookmark (list_id, entry_id, entry_comment_id, post_id, post_comment_id)');
        $this->addSql('COMMENT ON COLUMN bookmark.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE bookmark_list (id INT NOT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, is_default BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A650C0C4A76ED395 ON bookmark_list (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A650C0C4A76ED3955E237E06 ON bookmark_list (user_id, name)');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D3DAE168B FOREIGN KEY (list_id) REFERENCES bookmark_list (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921DBA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D60C33421 FOREIGN KEY (entry_comment_id) REFERENCES entry_comment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921DDB1174D2 FOREIGN KEY (post_comment_id) REFERENCES post_comment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark_list ADD CONSTRAINT FK_A650C0C4A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE bookmark_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE bookmark_list_id_seq CASCADE');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921D3DAE168B');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921DA76ED395');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921DBA364942');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921D60C33421');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921D4B89032C');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921DDB1174D2');
        $this->addSql('ALTER TABLE bookmark_list DROP CONSTRAINT FK_A650C0C4A76ED395');
        $this->addSql('DROP TABLE bookmark');
        $this->addSql('DROP TABLE bookmark_list');
    }
}
