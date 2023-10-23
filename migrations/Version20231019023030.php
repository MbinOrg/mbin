<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231019023030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE cardano_tx_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE cardano_tx_init_id_seq CASCADE');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE cardano_tx DROP CONSTRAINT fk_f74c620e3eb84a1d');
        $this->addSql('ALTER TABLE cardano_tx DROP CONSTRAINT fk_f74c620ecd53edb6');
        $this->addSql('ALTER TABLE cardano_tx DROP CONSTRAINT fk_f74c620ef624b39d');
        $this->addSql('ALTER TABLE cardano_tx DROP CONSTRAINT fk_f74c620eba364942');
        $this->addSql('ALTER TABLE cardano_tx_init DROP CONSTRAINT fk_973316583eb84a1d');
        $this->addSql('ALTER TABLE cardano_tx_init DROP CONSTRAINT fk_97331658a76ed395');
        $this->addSql('ALTER TABLE cardano_tx_init DROP CONSTRAINT fk_97331658ba364942');
        $this->addSql('DROP TABLE rememberme_token');
        $this->addSql('DROP TABLE cardano_tx');
        $this->addSql('DROP TABLE cardano_tx_init');
        $this->addSql('ALTER TABLE "user" DROP cardano_wallet_id');
        $this->addSql('ALTER TABLE "user" DROP cardano_wallet_address');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE cardano_tx_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE cardano_tx_init_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE rememberme_token (series VARCHAR(88) NOT NULL, value VARCHAR(88) NOT NULL, lastused TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, class VARCHAR(100) NOT NULL, username VARCHAR(200) NOT NULL, PRIMARY KEY(series))');
        $this->addSql('CREATE TABLE cardano_tx (id INT NOT NULL, magazine_id INT DEFAULT NULL, receiver_id INT DEFAULT NULL, sender_id INT DEFAULT NULL, entry_id INT DEFAULT NULL, amount INT NOT NULL, tx_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ctx_type TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_f74c620eba364942 ON cardano_tx (entry_id)');
        $this->addSql('CREATE INDEX idx_f74c620ef624b39d ON cardano_tx (sender_id)');
        $this->addSql('CREATE INDEX idx_f74c620ecd53edb6 ON cardano_tx (receiver_id)');
        $this->addSql('CREATE INDEX idx_f74c620e3eb84a1d ON cardano_tx (magazine_id)');
        $this->addSql('COMMENT ON COLUMN cardano_tx.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE cardano_tx_init (id INT NOT NULL, magazine_id INT DEFAULT NULL, user_id INT DEFAULT NULL, entry_id INT DEFAULT NULL, session_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, cpi_type TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_97331658ba364942 ON cardano_tx_init (entry_id)');
        $this->addSql('CREATE INDEX idx_97331658a76ed395 ON cardano_tx_init (user_id)');
        $this->addSql('CREATE INDEX idx_973316583eb84a1d ON cardano_tx_init (magazine_id)');
        $this->addSql('COMMENT ON COLUMN cardano_tx_init.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE cardano_tx ADD CONSTRAINT fk_f74c620e3eb84a1d FOREIGN KEY (magazine_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cardano_tx ADD CONSTRAINT fk_f74c620ecd53edb6 FOREIGN KEY (receiver_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cardano_tx ADD CONSTRAINT fk_f74c620ef624b39d FOREIGN KEY (sender_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cardano_tx ADD CONSTRAINT fk_f74c620eba364942 FOREIGN KEY (entry_id) REFERENCES entry (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cardano_tx_init ADD CONSTRAINT fk_973316583eb84a1d FOREIGN KEY (magazine_id) REFERENCES magazine (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cardano_tx_init ADD CONSTRAINT fk_97331658a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cardano_tx_init ADD CONSTRAINT fk_97331658ba364942 FOREIGN KEY (entry_id) REFERENCES entry (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE "user" ADD cardano_wallet_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD cardano_wallet_address VARCHAR(255) DEFAULT NULL');
    }
}
