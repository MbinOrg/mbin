<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251121203418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Run forgotten migration diff after major package releases upgrade';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE entry ALTER title_ts SET DEFAULT \'english\'');
        $this->addSql('ALTER TABLE entry ALTER body_ts SET DEFAULT \'english\'');
        $this->addSql('ALTER TABLE entry_comment ALTER body_ts SET DEFAULT \'english\'');
        $this->addSql('ALTER TABLE instance ALTER last_successful_deliver TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE instance ALTER last_successful_receive TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE instance ALTER last_failed_deliver TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_deliver IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_receive IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_failed_deliver IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE message ALTER uuid DROP DEFAULT');
        $this->addSql('ALTER TABLE message_thread ALTER updated_at DROP NOT NULL');
        $this->addSql('ALTER TABLE notification_settings ALTER notification_status TYPE ENUM(\'Default\', \'Muted\', \'Loud\')');
        $this->addSql('COMMENT ON COLUMN notification_settings.notification_status IS \'(DC2Type:EnumNotificationStatus)\'');
        $this->addSql('ALTER TABLE post ALTER body_ts SET DEFAULT \'english\'');
        $this->addSql('ALTER TABLE post_comment ALTER body_ts SET DEFAULT \'english\'');
        $this->addSql('ALTER TABLE "user" ALTER application_status TYPE ENUM(\'Approved\', \'Rejected\', \'Pending\')');
        $this->addSql('ALTER TABLE "user" ALTER notify_on_user_signup SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER front_default_sort TYPE ENUM(\'hot\', \'top\', \'newest\', \'active\', \'oldest\', \'commented\')');
        $this->addSql('ALTER TABLE "user" ALTER comment_default_sort TYPE ENUM(\'hot\', \'top\', \'newest\', \'active\', \'oldest\', \'commented\')');
        $this->addSql('ALTER TABLE "user" ALTER direct_message_setting TYPE ENUM(\'everyone\', \'followers_only\', \'nobody\')');
        $this->addSql('ALTER TABLE "user" ALTER front_default_content TYPE ENUM(\'all\', \'threads\', \'microblog\', \'\')');
        $this->addSql('COMMENT ON COLUMN "user".application_status IS \'(DC2Type:EnumApplicationStatus)\'');
        $this->addSql('COMMENT ON COLUMN "user".front_default_sort IS \'(DC2Type:EnumSortOptions)\'');
        $this->addSql('COMMENT ON COLUMN "user".comment_default_sort IS \'(DC2Type:EnumSortOptions)\'');
        $this->addSql('COMMENT ON COLUMN "user".direct_message_setting IS \'(DC2Type:EnumDirectMessageSettings)\'');
        $this->addSql('COMMENT ON COLUMN "user".front_default_content IS \'(DC2Type:EnumFrontContentOptions)\'');
        $this->addSql('CREATE UNIQUE INDEX user_ap_public_url_idx ON "user" (ap_public_url)');
        $this->addSql('ALTER TABLE rememberme_token ALTER lastused TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN rememberme_token.lastUsed IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE post ALTER body_ts DROP DEFAULT');
        $this->addSql('DROP INDEX user_ap_public_url_idx');
        $this->addSql('ALTER TABLE "user" ALTER front_default_sort TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "user" ALTER front_default_content TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "user" ALTER comment_default_sort TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "user" ALTER direct_message_setting TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "user" ALTER notify_on_user_signup DROP NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER application_status TYPE VARCHAR(255)');
        $this->addSql('COMMENT ON COLUMN "user".front_default_sort IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".front_default_content IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".comment_default_sort IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".direct_message_setting IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".application_status IS NULL');
        $this->addSql('ALTER TABLE message_thread ALTER updated_at SET NOT NULL');
        $this->addSql('ALTER TABLE entry_comment ALTER body_ts DROP DEFAULT');
        $this->addSql('ALTER TABLE instance ALTER last_successful_deliver TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE instance ALTER last_failed_deliver TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE instance ALTER last_successful_receive TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_deliver IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_failed_deliver IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_receive IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE entry ALTER title_ts DROP DEFAULT');
        $this->addSql('ALTER TABLE entry ALTER body_ts DROP DEFAULT');
        $this->addSql('ALTER TABLE post_comment ALTER body_ts DROP DEFAULT');
        $this->addSql('ALTER TABLE notification_settings ALTER notification_status TYPE VARCHAR(255)');
        $this->addSql('COMMENT ON COLUMN notification_settings.notification_status IS NULL');
        $this->addSql('ALTER TABLE rememberme_token ALTER lastUsed TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN rememberme_token.lastused IS NULL');
        $this->addSql('ALTER TABLE message ALTER uuid SET DEFAULT \'gen_random_uuid()\'');
    }
}
