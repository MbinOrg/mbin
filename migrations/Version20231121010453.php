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
    private const INDEXES = [
        'entry_ap_id_lower_idx' => ['table' => 'entry', 'column' => 'lower(ap_id)'],
        'entry_comment_ap_id_lower_idx' => ['table' => 'entry_comment', 'column' => 'lower(ap_id)'],
        'magazine_ap_id_lower_idx' => ['table' => 'magazine', 'column' => 'lower(ap_id)'],
        'magazine_ap_profile_id_lower_idx' => ['table' => 'magazine', 'column' => 'lower(ap_profile_id)'],
        'magazine_name_lower_idx' => ['table' => 'magazine', 'column' => 'lower(name)'],
        'magazine_title_lower_idx' => ['table' => 'magazine', 'column' => 'lower(title)'],
        'post_ap_id_lower_idx' => ['table' => 'post', 'column' => 'lower(ap_id)'],
        'post_comment_ap_id_lower_idx' => ['table' => 'post_comment', 'column' => 'lower(ap_id)'],
        'user_ap_id_lower_idx' => ['table' => 'user', 'column' => 'lower(ap_id)'],
        'user_ap_profile_id_lower_idx' => ['table' => 'user', 'column' => 'lower(ap_profile_id)'],
        'user_email_lower_idx' => ['table' => 'user', 'column' => 'lower(email)'],
        'user_username_lower_idx' => ['table' => 'user', 'column' => 'lower(username)'],
    ];

    public function getDescription(): string
    {
        return 'Introduce db optimizations - sync with /kbin, Mbin specific changes';
    }

    public function up(Schema $schema): void
    {
        foreach (self::INDEXES as $index => $details) {
            $this->addSql('CREATE INDEX '.$index.' ON "'.$details['table'].'" ('.$details['column'].')');
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::INDEXES as $index => $details) {
            $this->addSql('DROP INDEX '.$index);
        }
    }
}
