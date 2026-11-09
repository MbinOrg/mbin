<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029180327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns to user for federating custom css';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD custom_public_css TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD custom_public_css_integrity TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP custom_public_css_integrity');
        $this->addSql('ALTER TABLE "user" DROP custom_public_css');
    }
}
