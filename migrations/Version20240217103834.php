<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240217103834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This will make the file_path nullable, so we can store links to remote images, which could not be cached locally';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ALTER file_path DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ALTER file_path SET NOT NULL');
    }
}
