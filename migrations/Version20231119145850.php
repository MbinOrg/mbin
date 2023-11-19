<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231119145850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Improve db optimization';
    }

    public function up(Schema $schema): void
    {
        // Significant db performance improvement
        $this->addSql('CREATE INDEX user_username_lower_idx ON "user" (lower(username))');
        $this->addSql('CREATE INDEX user_email_lower_idx ON "user" (lower(email))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX user_username_lower_idx');
        $this->addSql('DROP INDEX user_email_lower_idx');
    }
}
