<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240529115400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove admin as the owner of remote magazines';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM moderator mod WHERE mod.is_owner = true AND EXISTS (SELECT * FROM magazine m WHERE mod.magazine_id = m.id AND m.ap_id IS NOT NULL);');
    }

    public function down(Schema $schema): void
    {
    }
}
