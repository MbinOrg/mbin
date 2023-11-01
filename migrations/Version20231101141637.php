<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231101141637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes Awards, unimplemented code.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE award_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE award_type_id_seq CASCADE');
        $this->addSql('ALTER TABLE award DROP CONSTRAINT fk_8a5b2ee7a76ed395');
        $this->addSql('ALTER TABLE award DROP CONSTRAINT fk_8a5b2ee73eb84a1d');
        $this->addSql('ALTER TABLE award DROP CONSTRAINT fk_8a5b2ee7c54c8c93');
        $this->addSql('DROP TABLE award_type');
        $this->addSql('DROP TABLE award');
        // Dropped Due to src/Entity/ApActivity.php, needs testing
        // $this->addSql('ALTER TABLE ap_activity DROP CONSTRAINT fk_68292518a76ed395');
        // $this->addSql('ALTER TABLE ap_activity DROP CONSTRAINT fk_682925183eb84a1d');
        // $this->addSql('DROP INDEX idx_682925183eb84a1d');
        // $this->addSql('DROP INDEX idx_68292518a76ed395');
        // $this->addSql('ALTER TABLE ap_activity DROP user_id');
        // $this->addSql('ALTER TABLE ap_activity DROP magazine_id');
        // $this->addSql('ALTER TABLE "user" ALTER type DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE award_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE award_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE award_type (id INT NOT NULL, name VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, count INT DEFAULT 0 NOT NULL, attributes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN award_type.attributes IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE award (id INT NOT NULL, user_id INT NOT NULL, magazine_id INT DEFAULT NULL, type_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_8a5b2ee7c54c8c93 ON award (type_id)');
        $this->addSql('CREATE INDEX idx_8a5b2ee73eb84a1d ON award (magazine_id)');
        $this->addSql('CREATE INDEX idx_8a5b2ee7a76ed395 ON award (user_id)');
        $this->addSql('COMMENT ON COLUMN award.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT fk_8a5b2ee7a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT fk_8a5b2ee73eb84a1d FOREIGN KEY (magazine_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT fk_8a5b2ee7c54c8c93 FOREIGN KEY (type_id) REFERENCES award_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Altered Due to src/Entity/ApActivity.php, needs testing
        // $this->addSql('ALTER TABLE ap_activity ADD user_id INT NOT NULL');
        // $this->addSql('ALTER TABLE ap_activity ADD magazine_id INT DEFAULT NULL');
        // $this->addSql('ALTER TABLE ap_activity ADD CONSTRAINT fk_68292518a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // $this->addSql('ALTER TABLE ap_activity ADD CONSTRAINT fk_682925183eb84a1d FOREIGN KEY (magazine_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // $this->addSql('CREATE INDEX idx_682925183eb84a1d ON ap_activity (magazine_id)');
        // $this->addSql('CREATE INDEX idx_68292518a76ed395 ON ap_activity (user_id)');
        // $this->addSql('ALTER TABLE "user" ALTER type SET DEFAULT \'Person\'');
    }
}
