<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240612234046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Privacy Portal SSO';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD oauth_privacyportal_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP oauth_privacyportal_id');
    }
}
