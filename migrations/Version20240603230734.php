<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240603230734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Authentik SSO';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD oauth_authentik_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP oauth_authentik_id');
    }
}
