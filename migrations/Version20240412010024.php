<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240412010024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix on delete to cascade for magazine_ban and magazine_log tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE magazine_ban DROP CONSTRAINT FK_6A126CE5386B8E7');
        $this->addSql('ALTER TABLE magazine_ban ADD CONSTRAINT FK_6A126CE5386B8E7 FOREIGN KEY (banned_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE magazine_log DROP CONSTRAINT FK_87D3D4C5A76ED395');
        $this->addSql('ALTER TABLE magazine_log ADD CONSTRAINT FK_87D3D4C5A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE magazine_log DROP CONSTRAINT fk_87d3d4c5a76ed395');
        $this->addSql('ALTER TABLE magazine_log ADD CONSTRAINT fk_87d3d4c5a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE magazine_ban DROP CONSTRAINT fk_6a126ce5386b8e7');
        $this->addSql('ALTER TABLE magazine_ban ADD CONSTRAINT fk_6a126ce5386b8e7 FOREIGN KEY (banned_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
