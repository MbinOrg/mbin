<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231121010453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce some db optimizations - sync with /kbin, Mbin specific changes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX user_username_lower_idx ON "user" (lower(username))');
        $this->addSql('CREATE INDEX user_email_lower_idx ON "user" (lower(email))');
        $this->addSql('CREATE INDEX magazine_ap_id_lower_idx ON magazine (lower(ap_id))');
        $this->addSql('CREATE INDEX magazine_ap_profile_id_lower_idx ON magazine (lower(ap_profile_id))');
        $this->addSql('CREATE INDEX user_ap_id_lower_idx ON "user" (lower(ap_id))');
        $this->addSql('CREATE INDEX user_ap_profile_id_lower_idx ON "user" (lower(ap_profile_id))');
        $this->addSql('CREATE INDEX magazine_name_lower_idx ON magazine (lower(name))');
        $this->addSql('CREATE INDEX magazine_title_lower_idx ON magazine (lower(title))');
        $this->addSql('CREATE INDEX entry_ap_id_lower_idx ON entry (lower(ap_id))');
        $this->addSql('CREATE INDEX entry_comment_ap_id_lower_idx ON entry_comment (lower(ap_id))');
        $this->addSql('CREATE INDEX post_ap_id_lower_idx ON post (lower(ap_id))');
        $this->addSql('CREATE INDEX post_comment_ap_id_lower_idx ON post_comment (lower(ap_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_username_lower_idx');
        $this->addSql('DROP INDEX user_email_lower_idx');
        $this->addSql('DROP INDEX magazine_ap_id_lower_idx');
        $this->addSql('DROP INDEX magazine_ap_profile_id_lower_idx');
        $this->addSql('DROP INDEX user_ap_id_lower_idx');
        $this->addSql('DROP INDEX user_ap_profile_id_lower_idx');
        $this->addSql('DROP INDEX magazine_name_lower_idx');
        $this->addSql('DROP INDEX magazine_title_lower_idx');
        $this->addSql('DROP INDEX entry_ap_id_lower_idx');
        $this->addSql('DROP INDEX entry_comment_ap_id_lower_idx');
        $this->addSql('DROP INDEX post_ap_id_lower_idx');
        $this->addSql('DROP INDEX post_comment_ap_id_lower_idx');
    }
}
