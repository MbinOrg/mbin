<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240628145441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the instance table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE instance_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE instance (id INT NOT NULL, software VARCHAR(255) DEFAULT NULL, version VARCHAR(255) DEFAULT NULL, domain VARCHAR(255) NOT NULL, last_successful_deliver TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, last_successful_receive TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, last_failed_deliver TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, failed_delivers INT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_deliver IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_failed_deliver IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.last_successful_receive IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN instance.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4230B1DEA7A91E0B ON instance (domain)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_4230B1DEA7A91E0B');
        $this->addSql('DROP SEQUENCE instance_id_seq CASCADE');
        $this->addSql('DROP TABLE instance');
    }
}
