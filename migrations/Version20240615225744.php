<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240615225744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a field to save the featured collection of magazines and users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine ADD ap_featured_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD ap_featured_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP ap_featured_url');
        $this->addSql('ALTER TABLE magazine DROP ap_featured_url');
    }
}
