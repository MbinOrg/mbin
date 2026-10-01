<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260809003139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'all changes which Doctrine generated after the upgrade';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ALTER last_boosted_at DROP DEFAULT');
        $this->addSql('ALTER TABLE entry_comment ALTER last_boosted_at DROP DEFAULT');
        $this->addSql('ALTER TABLE message ALTER uuid DROP DEFAULT');
        $this->addSql('ALTER TABLE message_thread ALTER updated_at DROP NOT NULL');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT fk_bf5476ca60c33421');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT fk_bf5476caba364942');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA60C33421 FOREIGN KEY (entry_comment_id) REFERENCES entry_comment (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CABA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE post ALTER last_boosted_at DROP DEFAULT');
        $this->addSql('ALTER TABLE post_comment ALTER last_boosted_at DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ALTER fields TYPE JSONB');
        $this->addSql('ALTER TABLE "user" ALTER notify_on_user_signup SET NOT NULL');

        $this->addSql('COMMENT ON COLUMN activity.uuid IS \'\'');
        $this->addSql('COMMENT ON COLUMN activity.inner_activity_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN activity.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN ap_activity.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN bookmark.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN domain_block.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN domain_subscription.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN embed.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN entry.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN entry.edited_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN entry_comment.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN entry_comment.edited_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN entry_comment_vote.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN entry_vote.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN favourite.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_deliver IS \'\'');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_receive IS \'\'');
        $this->addSql('COMMENT ON COLUMN instance.last_failed_deliver IS \'\'');
        $this->addSql('COMMENT ON COLUMN instance.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN instance.updated_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN magazine.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN magazine_ban.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN magazine_block.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN magazine_log.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN magazine_ownership_request.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN magazine_subscription.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN magazine_subscription_request.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN message.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN message.uuid IS \'\'');
        $this->addSql('COMMENT ON COLUMN message.edited_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN message_thread.updated_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN moderator.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN moderator_request.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN notification.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_access_token.expiry IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_access_token.scopes IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_authorization_code.expiry IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_authorization_code.scopes IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_client.redirect_uris IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_client.grants IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_client.scopes IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_client.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_client_access.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_refresh_token.expiry IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_user_consent.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN oauth2_user_consent.expires_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN post.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN post.edited_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN post_comment.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN post_comment.edited_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN post_comment_vote.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN post_vote.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN report.considered_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN report.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN "user".featured_magazines IS \'\'');
        $this->addSql('COMMENT ON COLUMN user_block.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN user_follow.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN user_follow_request.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN user_note.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN user_push_subscription.device_key IS \'\'');

        /* TODO always comment these migrations, as they are faulty
         * $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
         * $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
         *
         * $this->addSql('DROP TABLE sessions');
         *
         * $this->addSql('ALTER TABLE entry ALTER title_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE entry ALTER body_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE entry_comment ALTER body_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE magazine ALTER name_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE magazine ALTER title_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE magazine ALTER description_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE post ALTER body_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE post_comment ALTER body_ts SET DEFAULT \'english\'');
         * $this->addSql('ALTER TABLE "user" ALTER username_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE "user" ALTER about_ts DROP DEFAULT');
         * $this->addSql('ALTER TABLE "user" ALTER title_ts DROP DEFAULT');
         */
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ALTER last_boosted_at SET DEFAULT \'(to_timestamp((0)::double precision))::date\'');
        $this->addSql('ALTER TABLE entry_comment ALTER last_boosted_at SET DEFAULT \'(to_timestamp((0)::double precision))::date\'');
        $this->addSql('ALTER TABLE message ALTER uuid SET DEFAULT \'gen_random_uuid()\'');
        $this->addSql('ALTER TABLE message_thread ALTER updated_at SET NOT NULL');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CABA364942');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA60C33421');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT fk_bf5476caba364942 FOREIGN KEY (entry_id) REFERENCES entry (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT fk_bf5476ca60c33421 FOREIGN KEY (entry_comment_id) REFERENCES entry_comment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE post ALTER last_boosted_at SET DEFAULT \'(to_timestamp((0)::double precision))::date\'');
        $this->addSql('ALTER TABLE post_comment ALTER last_boosted_at SET DEFAULT \'(to_timestamp((0)::double precision))::date\'');
        $this->addSql('ALTER TABLE "user" ALTER fields TYPE JSON');
        $this->addSql('ALTER TABLE "user" ALTER notify_on_user_signup DROP NOT NULL');

        $this->addSql('COMMENT ON COLUMN activity.uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN activity.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN activity.inner_activity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN ap_activity.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bookmark.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN domain_block.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN domain_subscription.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN embed.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN entry.edited_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN entry.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN entry_comment.edited_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN entry_comment.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN entry_comment_vote.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN entry_vote.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN favourite.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_deliver IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_failed_deliver IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_receive IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN magazine.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN magazine_ban.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN magazine_block.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN magazine_log.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN magazine_ownership_request.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN magazine_subscription.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN magazine_subscription_request.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN message.uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN message.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN message.edited_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN message_thread.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN moderator.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN moderator_request.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notification.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN oauth2_access_token.expiry IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN oauth2_access_token.scopes IS \'(DC2Type:oauth2_scope)\'');
        $this->addSql('COMMENT ON COLUMN oauth2_authorization_code.expiry IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN oauth2_authorization_code.scopes IS \'(DC2Type:oauth2_scope)\'');
        $this->addSql('COMMENT ON COLUMN "oauth2_client".redirect_uris IS \'(DC2Type:oauth2_redirect_uri)\'');
        $this->addSql('COMMENT ON COLUMN "oauth2_client".grants IS \'(DC2Type:oauth2_grant)\'');
        $this->addSql('COMMENT ON COLUMN "oauth2_client".scopes IS \'(DC2Type:oauth2_scope)\'');
        $this->addSql('COMMENT ON COLUMN "oauth2_client".created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN oauth2_client_access.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN oauth2_refresh_token.expiry IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN oauth2_user_consent.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN oauth2_user_consent.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN post.edited_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN post.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN post_comment.edited_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN post_comment.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN post_comment_vote.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN post_vote.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report.considered_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".featured_magazines IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_block.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_follow.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_follow_request.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_note.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_push_subscription.device_key IS \'(DC2Type:uuid)\'');

        /* always comment these migrations, as they are faulty
         * $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL, sess_data BYTEA NOT NULL, sess_lifetime INT NOT NULL, sess_time INT NOT NULL, PRIMARY KEY (sess_id))');
         * $this->addSql('CREATE INDEX sess_lifetime_idx ON sessions (sess_lifetime)');
         *
         * $this->addSql('DROP TABLE messenger_messages');
         *
         * $this->addSql('ALTER TABLE entry ALTER title_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (title)::text)\'');
         * $this->addSql('ALTER TABLE entry ALTER body_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, body)\'');
         * $this->addSql('ALTER TABLE entry_comment ALTER body_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, body)\'');
         * $this->addSql('ALTER TABLE magazine ALTER name_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (name)::text)\'');
         * $this->addSql('ALTER TABLE magazine ALTER title_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (title)::text)\'');
         * $this->addSql('ALTER TABLE magazine ALTER description_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, description)\'');
         * $this->addSql('ALTER TABLE post ALTER body_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, body)\'');
         * $this->addSql('ALTER TABLE post_comment ALTER body_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, body)\'');
         * $this->addSql('ALTER TABLE "user" ALTER username_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (username)::text)\'');
         * $this->addSql('ALTER TABLE "user" ALTER title_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, (title)::text)\'');
         * $this->addSql('ALTER TABLE "user" ALTER about_ts SET DEFAULT \'to_tsvector(\'\'english\'\'::regconfig, about)\'');
         */
    }
}
