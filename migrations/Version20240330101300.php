<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240330101300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This migration moves hashtags from the entry, entry_comment, post and post_comment table to its own table, while keeping the hashtag links alive';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS citext');
        $this->addSql('CREATE SEQUENCE hashtag_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE hashtag_link_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE hashtag (id INT NOT NULL, tag citext NOT NULL, banned BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5AB52A61389B783 ON hashtag (tag)');
        $this->addSql('CREATE TABLE hashtag_link (id INT NOT NULL, hashtag_id INT NOT NULL, entry_id INT DEFAULT NULL, entry_comment_id INT DEFAULT NULL, post_id INT DEFAULT NULL, post_comment_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_83957168FB34EF56 ON hashtag_link (hashtag_id)');
        $this->addSql('CREATE INDEX IDX_83957168BA364942 ON hashtag_link (entry_id)');
        $this->addSql('CREATE INDEX IDX_8395716860C33421 ON hashtag_link (entry_comment_id)');
        $this->addSql('CREATE INDEX IDX_839571684B89032C ON hashtag_link (post_id)');
        $this->addSql('CREATE INDEX IDX_83957168DB1174D2 ON hashtag_link (post_comment_id)');
        $this->addSql('ALTER TABLE hashtag_link ADD CONSTRAINT FK_83957168FB34EF56 FOREIGN KEY (hashtag_id) REFERENCES hashtag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hashtag_link ADD CONSTRAINT FK_83957168BA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hashtag_link ADD CONSTRAINT FK_8395716860C33421 FOREIGN KEY (entry_comment_id) REFERENCES entry_comment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hashtag_link ADD CONSTRAINT FK_839571684B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hashtag_link ADD CONSTRAINT FK_83957168DB1174D2 FOREIGN KEY (post_comment_id) REFERENCES post_comment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // migrate entry tags
        $select = "SELECT e.id, e.tags, keys.value::CITEXT as hashtag, e.created_at FROM entry e
                JOIN LATERAL (SELECT * FROM jsonb_array_elements_text(e.tags)) as keys ON TRUE
                WHERE e.tags IS NOT NULL AND jsonb_typeof(e.tags) = 'array'
            UNION ALL
            SELECT e.id, e.tags, keys.value::CITEXT as hashtag, e.created_at FROM entry e
                JOIN LATERAL (SELECT * FROM jsonb_each_text(e.tags)) as keys ON TRUE
                WHERE e.tags IS NOT NULL AND jsonb_typeof(e.tags) = 'object'
            ORDER BY created_at DESC";
        $foreachStatement = "IF NOT EXISTS (SELECT id FROM hashtag WHERE hashtag.tag = temprow.hashtag) THEN
                INSERT INTO hashtag(id, tag) VALUES(NEXTVAL('hashtag_id_seq'), temprow.hashtag);
            END IF;
            IF NOT EXISTS (SELECT l.id FROM hashtag_link l 
                INNER JOIN hashtag def ON def.id=l.hashtag_id 
                WHERE l.entry_id = temprow.id AND def.tag = temprow.hashtag) 
            THEN
                INSERT INTO hashtag_link (id, entry_id, hashtag_id) VALUES (NEXTVAL('hashtag_link_id_seq'), temprow.id, (SELECT id FROM hashtag WHERE tag = temprow.hashtag));
            END IF;";

        $this->addSql('DO
            $do$
                declare temprow record;
            BEGIN 
                FOR temprow IN
                    '.$select.'
                LOOP
                    '.$foreachStatement.'
                END LOOP;
            END
            $do$;');

        // migrate entry comments tags
        $select = "SELECT e.id, e.tags, keys.value::CITEXT as hashtag, e.created_at FROM entry_comment e
                JOIN LATERAL (SELECT * FROM jsonb_array_elements_text(e.tags)) as keys ON TRUE
                WHERE e.tags IS NOT NULL AND jsonb_typeof(e.tags) = 'array'
            UNION ALL
            SELECT e.id, e.tags, keys.value::CITEXT as hashtag, e.created_at FROM entry_comment e
                JOIN LATERAL (SELECT * FROM jsonb_each_text(e.tags)) as keys ON TRUE
                WHERE e.tags IS NOT NULL AND jsonb_typeof(e.tags) = 'object'
            ORDER BY created_at DESC";
        $foreachStatement = "IF NOT EXISTS (SELECT id FROM hashtag WHERE hashtag.tag = temprow.hashtag) THEN
                INSERT INTO hashtag(id, tag) VALUES(NEXTVAL('hashtag_id_seq'), temprow.hashtag);
            END IF;
            IF NOT EXISTS (SELECT l.id FROM hashtag_link l 
                INNER JOIN hashtag def ON def.id=l.hashtag_id 
                WHERE l.entry_comment_id = temprow.id AND def.tag = temprow.hashtag) 
            THEN
                INSERT INTO hashtag_link (id, entry_comment_id, hashtag_id) VALUES (NEXTVAL('hashtag_link_id_seq'), temprow.id, (SELECT id FROM hashtag WHERE tag=temprow.hashtag));
            END IF;";

        $this->addSql('DO
            $do$
                declare temprow record;
            BEGIN 
                FOR temprow IN
                    '.$select.'
                LOOP
                    '.$foreachStatement.'
                END LOOP;
            END
            $do$;');

        // migrate post tags
        $select = "SELECT e.id, e.tags, keys.value::CITEXT as hashtag, e.created_at FROM post e
                JOIN LATERAL (SELECT * FROM jsonb_array_elements_text(e.tags)) as keys ON TRUE
                WHERE e.tags IS NOT NULL AND jsonb_typeof(e.tags) = 'array'
            UNION ALL
            SELECT e.id, e.tags, keys.value::CITEXT as hashtag, e.created_at FROM post e
                JOIN LATERAL (SELECT * FROM jsonb_each_text(e.tags)) as keys ON TRUE
                WHERE e.tags IS NOT NULL AND jsonb_typeof(e.tags) = 'object'
            ORDER BY created_at DESC";
        $foreachStatement = "IF NOT EXISTS (SELECT id FROM hashtag WHERE hashtag.tag = temprow.hashtag) THEN
                INSERT INTO hashtag(id, tag) VALUES(NEXTVAL('hashtag_id_seq'), temprow.hashtag);
            END IF;
            IF NOT EXISTS (SELECT l.id FROM hashtag_link l 
                INNER JOIN hashtag def ON def.id=l.hashtag_id 
                WHERE l.post_id = temprow.id AND def.tag = temprow.hashtag) 
            THEN
                INSERT INTO hashtag_link (id, post_id, hashtag_id) VALUES (NEXTVAL('hashtag_link_id_seq'), temprow.id, (SELECT id FROM hashtag WHERE tag=temprow.hashtag));
            END IF;";

        $this->addSql('DO
            $do$
                declare temprow record;
            BEGIN 
                FOR temprow IN
                    '.$select.'
                LOOP
                    '.$foreachStatement.'
                END LOOP;
            END
            $do$;');
        // migrate post comment tags
        $select = "SELECT e.id, e.tags, keys.value::CITEXT as hashtag, e.created_at FROM post_comment e
                JOIN LATERAL (SELECT * FROM jsonb_array_elements_text(e.tags)) as keys ON TRUE
                WHERE e.tags IS NOT NULL AND jsonb_typeof(e.tags) = 'array'
            UNION ALL
            SELECT e.id, e.tags, keys.value::CITEXT as hashtag, e.created_at FROM post_comment e
                JOIN LATERAL (SELECT * FROM jsonb_each_text(e.tags)) as keys ON TRUE
                WHERE e.tags IS NOT NULL AND jsonb_typeof(e.tags) = 'object'
            ORDER BY created_at DESC";
        $foreachStatement = "IF NOT EXISTS (SELECT id FROM hashtag WHERE hashtag.tag = temprow.hashtag) THEN
                INSERT INTO hashtag(id, tag) VALUES(NEXTVAL('hashtag_id_seq'), temprow.hashtag);
            END IF;
            IF NOT EXISTS (SELECT l.id FROM hashtag_link l 
                INNER JOIN hashtag def ON def.id=l.hashtag_id 
                WHERE l.post_comment_id = temprow.id AND def.tag = temprow.hashtag) 
            THEN
                INSERT INTO hashtag_link (id, post_comment_id, hashtag_id) VALUES (NEXTVAL('hashtag_link_id_seq'), temprow.id, (SELECT id FROM hashtag WHERE tag=temprow.hashtag));
            END IF;";

        $this->addSql('DO
            $do$
                declare temprow record;
            BEGIN 
                FOR temprow IN
                    '.$select.'
                LOOP
                    '.$foreachStatement.'
                END LOOP;
            END
            $do$;');

        $this->addSql('ALTER TABLE entry DROP COLUMN tags');
        $this->addSql('ALTER TABLE entry_comment DROP COLUMN tags');
        $this->addSql('ALTER TABLE post DROP COLUMN tags');
        $this->addSql('ALTER TABLE post_comment DROP COLUMN tags');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry_comment ADD tags JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE post_comment ADD tags JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD tags JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD tags JSONB DEFAULT NULL');

        $this->addSql('DO
$do$
    declare temprow record;
BEGIN 
    FOR temprow IN
        SELECT hl.entry_id, hl.entry_comment_id, hl.post_id, hl.post_comment_id, h.tag FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id
    LOOP
        IF temprow.entry_id IS NOT NULL THEN    
            IF NOT EXISTS (SELECT id FROM entry e WHERE e.id = temprow.entry_id AND e.tags IS NOT NULL) THEN
                UPDATE entry SET tags = \'[]\'::jsonb WHERE entry.id = temprow.entry_id;
            END IF;
            UPDATE entry SET tags = tags || to_jsonb(temprow.tag) WHERE entry.id = temprow.entry_id;
        END IF;
        IF temprow.entry_comment_id IS NOT NULL THEN
            IF NOT EXISTS (SELECT id FROM entry_comment ec WHERE ec.id = temprow.entry_comment_id AND ec.tags IS NOT NULL) THEN
                UPDATE entry_comment SET tags = \'[]\'::jsonb WHERE entry_comment.id = temprow.entry_comment_id;
            END IF;
            UPDATE entry_comment SET tags = tags || to_jsonb(temprow.tag) WHERE entry_comment.id = temprow.entry_comment_id;
        END IF;
        IF temprow.post_id IS NOT NULL THEN
            IF NOT EXISTS (SELECT id FROM post p WHERE p.id = temprow.post_id AND p.tags IS NOT NULL) THEN
                UPDATE post SET tags = \'[]\'::jsonb WHERE post.id = temprow.post_id;
            END IF;
            UPDATE post SET tags = tags || to_jsonb(temprow.tag) WHERE post.id = temprow.post_id;
        END IF;
        IF temprow.post_comment_id IS NOT NULL THEN
            IF NOT EXISTS (SELECT id FROM post_comment pc WHERE pc.id = temprow.post_comment_id AND pc.tags IS NOT NULL) THEN
                UPDATE post_comment SET tags = \'[]\'::jsonb WHERE post_comment.id = temprow.post_comment_id;
            END IF;
            UPDATE post_comment SET tags = tags || to_jsonb(temprow.tag) WHERE post_comment.id = temprow.post_comment_id;
        END IF;
    END LOOP;
END
$do$;');

        $this->addSql('DROP SEQUENCE hashtag_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE hashtag_link_id_seq CASCADE');
        $this->addSql('ALTER TABLE hashtag_link DROP CONSTRAINT FK_83957168FB34EF56');
        $this->addSql('ALTER TABLE hashtag_link DROP CONSTRAINT FK_83957168BA364942');
        $this->addSql('ALTER TABLE hashtag_link DROP CONSTRAINT FK_8395716860C33421');
        $this->addSql('ALTER TABLE hashtag_link DROP CONSTRAINT FK_839571684B89032C');
        $this->addSql('ALTER TABLE hashtag_link DROP CONSTRAINT FK_83957168DB1174D2');
        $this->addSql('DROP TABLE hashtag');
        $this->addSql('DROP TABLE hashtag_link');
    }
}
