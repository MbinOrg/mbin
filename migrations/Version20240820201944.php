<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240820201944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the activity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity (uuid UUID NOT NULL, user_actor_id INT DEFAULT NULL, magazine_actor_id INT DEFAULT NULL, audience_id INT DEFAULT NULL, inner_activity_id UUID DEFAULT NULL, object_entry_id INT DEFAULT NULL, object_entry_comment_id INT DEFAULT NULL, object_post_id INT DEFAULT NULL, object_post_comment_id INT DEFAULT NULL, object_message_id INT DEFAULT NULL, object_user_id INT DEFAULT NULL, object_magazine_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, inner_activity_url TEXT DEFAULT NULL, object_generic TEXT DEFAULT NULL, target_string TEXT DEFAULT NULL, content_string TEXT DEFAULT NULL, activity_json TEXT DEFAULT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_AC74095AF057164A ON activity (user_actor_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A2F5FA0A4 ON activity (magazine_actor_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A848CC616 ON activity (audience_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A1B4C3858 ON activity (inner_activity_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A6CE0A42A ON activity (object_entry_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC3683D33 ON activity (object_entry_comment_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A4BC7838C ON activity (object_post_id)');
        $this->addSql('CREATE INDEX IDX_AC74095ACC1812B0 ON activity (object_post_comment_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A20E5BA95 ON activity (object_message_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AA7205335 ON activity (object_user_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AFC1C2A13 ON activity (object_magazine_id)');
        $this->addSql('COMMENT ON COLUMN activity.uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN activity.inner_activity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AF057164A FOREIGN KEY (user_actor_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A2F5FA0A4 FOREIGN KEY (magazine_actor_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A848CC616 FOREIGN KEY (audience_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A1B4C3858 FOREIGN KEY (inner_activity_id) REFERENCES activity (uuid) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A6CE0A42A FOREIGN KEY (object_entry_id) REFERENCES entry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AC3683D33 FOREIGN KEY (object_entry_comment_id) REFERENCES entry_comment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A4BC7838C FOREIGN KEY (object_post_id) REFERENCES post (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095ACC1812B0 FOREIGN KEY (object_post_comment_id) REFERENCES post_comment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A20E5BA95 FOREIGN KEY (object_message_id) REFERENCES message (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AA7205335 FOREIGN KEY (object_user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AFC1C2A13 FOREIGN KEY (object_magazine_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AF057164A');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A2F5FA0A4');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A848CC616');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A1B4C3858');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A6CE0A42A');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AC3683D33');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A4BC7838C');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095ACC1812B0');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A20E5BA95');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AA7205335');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AFC1C2A13');
        $this->addSql('DROP TABLE activity');
    }
}
