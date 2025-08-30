<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250723183702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add magazine ban as an object to the activity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD object_magazine_ban_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AE490E490 FOREIGN KEY (object_magazine_ban_id) REFERENCES magazine_ban (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AC74095AE490E490 ON activity (object_magazine_ban_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AE490E490');
        $this->addSql('DROP INDEX IDX_AC74095AE490E490');
        $this->addSql('ALTER TABLE activity DROP object_magazine_ban_id');
    }
}
