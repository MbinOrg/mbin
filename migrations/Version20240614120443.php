<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240614120443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns for remote likes, dislikes and shares';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ADD ap_like_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD ap_dislike_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD ap_share_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry_comment ADD ap_like_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry_comment ADD ap_dislike_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry_comment ADD ap_share_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD ap_like_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD ap_dislike_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD ap_share_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post_comment ADD ap_like_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post_comment ADD ap_dislike_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post_comment ADD ap_share_count INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry_comment DROP ap_like_count');
        $this->addSql('ALTER TABLE entry_comment DROP ap_dislike_count');
        $this->addSql('ALTER TABLE entry_comment DROP ap_share_count');
        $this->addSql('ALTER TABLE post_comment DROP ap_like_count');
        $this->addSql('ALTER TABLE post_comment DROP ap_dislike_count');
        $this->addSql('ALTER TABLE post_comment DROP ap_share_count');
        $this->addSql('ALTER TABLE post DROP ap_like_count');
        $this->addSql('ALTER TABLE post DROP ap_dislike_count');
        $this->addSql('ALTER TABLE post DROP ap_share_count');
        $this->addSql('ALTER TABLE entry DROP ap_like_count');
        $this->addSql('ALTER TABLE entry DROP ap_dislike_count');
        $this->addSql('ALTER TABLE entry DROP ap_share_count');
    }
}
