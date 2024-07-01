<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Entry;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240701113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'fix the type of posts without a url, but with an image';
    }

    public function up(Schema $schema): void
    {
        $type = Entry::ENTRY_TYPE_IMAGE;
        $this->addSql("UPDATE entry SET type = '$type' WHERE image_id IS NOT NULL AND url IS NULL");
    }

    public function down(Schema $schema): void
    {
    }
}
