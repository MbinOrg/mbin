<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251206145724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove old MAX_IMAGE_BYTES unused setting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM settings WHERE name=\'MAX_IMAGE_BYTES\'');
    }

    public function down(Schema $schema): void
    {
    }
}
