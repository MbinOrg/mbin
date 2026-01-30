<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260118142727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on the magazine table, rename an old one';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX magazine_ap_profile_id_idx ON magazine (ap_profile_id)');
        $this->addSql('CREATE UNIQUE INDEX magazine_ap_public_url_idx ON magazine (ap_public_url)');
        $this->addSql('ALTER INDEX uniq_378c2fe4904f155e RENAME TO magazine_ap_id_idx');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX magazine_ap_profile_id_idx');
        $this->addSql('DROP INDEX magazine_ap_public_url_idx');
        $this->addSql('ALTER INDEX magazine_ap_id_idx RENAME TO uniq_378c2fe4904f155e');
    }
}
