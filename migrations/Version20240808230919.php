<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240808230919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user IP column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD ip VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP ip');
    }
}
