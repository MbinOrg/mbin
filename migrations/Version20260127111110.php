<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260127111110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove duplicate indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_da62921d3dae168b'); // bookmark (list_id) -> covered by bookmark_list_entry_entrycomment_post_postcomment_idx (list_id, entry_id, entry_comment_id, post_id, post_comment_id)
        $this->addSql('DROP INDEX IF EXISTS idx_a650c0c4a76ed395'); // bookmark_list (user_id) -> covered by uniq_a650c0c4a76ed3955e237e06 (user_id, name)
        $this->addSql('DROP INDEX IF EXISTS idx_5060bff4a76ed395'); // domain_block (user_id) -> covered by domain_block_idx (user_id, domain_id)
        $this->addSql('DROP INDEX IF EXISTS idx_3ac9125ea76ed395'); // domain_subscription (user_id) -> covered by domain_subscription_idx (user_id, domain_id)
        $this->addSql('DROP INDEX IF EXISTS entry_visibility_idx'); // entry (visibility) -> covered by entry_visibility_adult_idx (visibility, is_adult)
        $this->addSql('DROP INDEX IF EXISTS idx_9e561267a76ed395'); // entry_comment_vote (user_id) -> covered by user_entry_comment_vote_idx (user_id, comment_id)
        $this->addSql('DROP INDEX IF EXISTS idx_fe32fd77a76ed395'); // entry_vote (user_id) -> covered by user_entry_vote_idx (user_id, entry_id)
        $this->addSql('DROP INDEX IF EXISTS idx_62a2ca1960c33421'); // favourite (entry_comment_id) -> covered by favourite_user_entry_comment_unique_idx (entry_comment_id, user_id)
        $this->addSql('DROP INDEX IF EXISTS idx_62a2ca19ba364942'); // favourite (entry_id) -> covered by favourite_user_entry_unique_idx (entry_id, user_id)
        $this->addSql('DROP INDEX IF EXISTS idx_62a2ca19db1174d2'); // favourite (post_comment_id) -> covered by favourite_user_post_comment_unique_idx (post_comment_id, user_id)
        $this->addSql('DROP INDEX IF EXISTS idx_62a2ca194b89032c'); // favourite (post_id) -> covered by favourite_user_post_unique_idx (post_id, user_id)
        $this->addSql('DROP INDEX IF EXISTS magazine_visibility_idx'); // magazine (visibility) -> covered by magazine_visibility_adult_idx (visibility, is_adult)
        $this->addSql('DROP INDEX IF EXISTS idx_41cc6069a76ed395'); // magazine_block (user_id) -> covered by magazine_block_idx (user_id, magazine_id)
        $this->addSql('DROP INDEX IF EXISTS idx_a7160c653eb84a1d'); // magazine_ownership_request (magazine_id) -> covered by magazine_ownership_magazine_user_idx (magazine_id, user_id)
        $this->addSql('DROP INDEX IF EXISTS idx_acce935a76ed395'); // magazine_subscription (user_id) -> covered by magazine_subsription_idx (user_id, magazine_id)
        $this->addSql('DROP INDEX IF EXISTS idx_38501651a76ed395'); // magazine_subscription_request (user_id) -> covered by magazine_subscription_requests_idx (user_id, magazine_id)
        $this->addSql('DROP INDEX IF EXISTS idx_f2de92908829462f'); // message_thread_participants (message_thread_id) -> covered by message_thread_participants_pkey (message_thread_id, user_id)
        $this->addSql('DROP INDEX IF EXISTS idx_6a30b2683eb84a1d'); // moderator (magazine_id) -> covered by moderator_magazine_user_idx (magazine_id, user_id)
        $this->addSql('DROP INDEX IF EXISTS idx_2cc3e3243eb84a1d'); // moderator_request (magazine_id) -> covered by moderator_request_magazine_user_idx (magazine_id, user_id)
        $this->addSql('DROP INDEX IF EXISTS idx_b0559860a76ed395'); // notification_settings (user_id) -> covered by notification_settings_user_target (user_id, entry_id, post_id, magazine_id, target_user_id)
        $this->addSql('DROP INDEX IF EXISTS post_visibility_idx'); // post (visibility) -> covered by post_visibility_adult_idx (visibility, is_adult)
        $this->addSql('DROP INDEX IF EXISTS idx_d71b5a5ba76ed395'); // post_comment_vote (user_id) -> covered by user_post_comment_vote_idx (user_id, comment_id)
        $this->addSql('DROP INDEX IF EXISTS idx_9345e26fa76ed395'); // post_vote (user_id) -> covered by user_post_vote_idx (user_id, post_id)
        $this->addSql('DROP INDEX IF EXISTS idx_61d96c7a548d5975'); // user_block (blocker_id) -> covered by user_block_idx (blocker_id, blocked_id)
        $this->addSql('DROP INDEX IF EXISTS idx_d665f4dac24f853'); // user_follow (follower_id) -> covered by user_follows_idx (follower_id, following_id)
        $this->addSql('DROP INDEX IF EXISTS idx_ee70876ac24f853'); // user_follow_request (follower_id) -> covered by user_follow_requests_idx (follower_id, following_id)
        $this->addSql('DROP INDEX IF EXISTS idx_b53cb6dda76ed395'); // user_note (user_id) -> covered by user_noted_idx (user_id, target_id)
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_DA62921D3DAE168B ON bookmark (list_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_A650C0C4A76ED395 ON bookmark_list (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_5060BFF4A76ED395 ON domain_block (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_3AC9125EA76ED395 ON domain_subscription (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS entry_visibility_idx ON entry (visibility)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_9E561267A76ED395 ON entry_comment_vote (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_62A2CA1960C33421 ON favourite (entry_comment_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_62A2CA19BA364942 ON favourite (entry_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_62A2CA19DB1174D2 ON favourite (post_comment_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_62A2CA194B89032C ON favourite (post_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS magazine_visibility_idx ON magazine (visibility)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_41CC6069A76ED395 ON magazine_block (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_A7160C653EB84A1D ON magazine_ownership_request (magazine_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_ACCE935A76ED395 ON magazine_subscription (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_38501651A76ED395 ON magazine_subscription_request (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_F2DE92908829462F ON message_thread_participants (message_thread_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_6A30B2683EB84A1D ON moderator (magazine_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_2CC3E3243EB84A1D ON moderator_request (magazine_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_B0559860A76ED395 ON notification_settings (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS post_visibility_idx ON post (visibility)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_D71B5A5BA76ED395 ON post_comment_vote (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_9345E26FA76ED395 ON post_vote (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_61D96C7A548D5975 ON user_block (blocker_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_D665F4DAC24F853 ON user_follow (follower_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_EE70876AC24F853 ON user_follow_request (follower_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_B53CB6DDA76ED395 ON user_note (user_id)');
    }
}
