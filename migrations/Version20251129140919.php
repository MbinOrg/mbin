<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251129140919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename value all to combined in front_default_content enum.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TYPE enumFrontContentOptions RENAME VALUE \'all\' TO \'combined\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TYPE enumFrontContentOptions RENAME VALUE \'combined\' TO \'all\'');
    }
}
