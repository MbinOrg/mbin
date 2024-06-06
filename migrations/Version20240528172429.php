<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240528172429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a field to the magazine log table for adding and removing a moderator';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine_log ADD acting_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE magazine_log ADD CONSTRAINT FK_87D3D4C53EAD8611 FOREIGN KEY (acting_user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_87D3D4C53EAD8611 ON magazine_log (acting_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine_log DROP CONSTRAINT FK_87D3D4C53EAD8611');
        $this->addSql('DROP INDEX IDX_87D3D4C53EAD8611');
        $this->addSql('ALTER TABLE magazine_log DROP acting_user_id');
    }
}
