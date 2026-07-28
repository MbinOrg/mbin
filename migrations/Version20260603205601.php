<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603205601 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add InstanceBlock';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE instance_block_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE instance_block (id INT NOT NULL, user_id INT NOT NULL, instance_id INT NOT NULL, instance_domain TEXT NOT NULL, blocked_by_admin BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX instance_block_idx ON instance_block (user_id, instance_domain)');
        $this->addSql('ALTER TABLE instance_block ADD CONSTRAINT FK_8F85864AA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE instance_block ADD CONSTRAINT FK_8F85864A3A51721D FOREIGN KEY (instance_id) REFERENCES instance (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE instance_block_id_seq CASCADE');
        $this->addSql('ALTER TABLE instance_block DROP CONSTRAINT FK_8F85864AA76ED395');
        $this->addSql('ALTER TABLE instance_block DROP CONSTRAINT FK_8F85864A3A51721D');
        $this->addSql('DROP TABLE instance_block');
    }
}
