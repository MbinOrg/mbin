<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231119012320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE award_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE award_type_id_seq CASCADE');
        $this->addSql('ALTER TABLE award DROP CONSTRAINT fk_8a5b2ee7a76ed395');
        $this->addSql('ALTER TABLE award DROP CONSTRAINT fk_8a5b2ee73eb84a1d');
        $this->addSql('ALTER TABLE award DROP CONSTRAINT fk_8a5b2ee7c54c8c93');
        $this->addSql('DROP TABLE award');
        $this->addSql('DROP TABLE award_type');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE award_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE award_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE award (id INT NOT NULL, user_id INT NOT NULL, magazine_id INT DEFAULT NULL, type_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_8a5b2ee7c54c8c93 ON award (type_id)');
        $this->addSql('CREATE INDEX idx_8a5b2ee73eb84a1d ON award (magazine_id)');
        $this->addSql('CREATE INDEX idx_8a5b2ee7a76ed395 ON award (user_id)');
        $this->addSql('COMMENT ON COLUMN award.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE award_type (id INT NOT NULL, name VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, count INT DEFAULT 0 NOT NULL, attributes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN award_type.attributes IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT fk_8a5b2ee7a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT fk_8a5b2ee73eb84a1d FOREIGN KEY (magazine_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT fk_8a5b2ee7c54c8c93 FOREIGN KEY (type_id) REFERENCES award_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
