<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240408161114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This migrations adds title, description, image and html columns to the embed table, so we can fetch them on demand and save them rather than to fetch them every time.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE embed ADD html TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE embed ADD title TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE embed ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE embed ADD image TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE embed DROP html');
        $this->addSql('ALTER TABLE embed DROP title');
        $this->addSql('ALTER TABLE embed DROP description');
        $this->addSql('ALTER TABLE embed DROP image');
    }
}
