<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260113103210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ap_indexable to ActivityPub actors';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE magazine ADD ap_indexable BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD ap_indexable BOOLEAN DEFAULT NULL');
        // The column should be nullable so that we know whether other software simply does not set this value,
        // but for local users and magazines we should only have true and false as options
        $this->addSql('UPDATE "user" SET ap_indexable = true WHERE ap_id IS NULL');
        $this->addSql('UPDATE magazine SET ap_indexable = true WHERE ap_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP ap_indexable');
        $this->addSql('ALTER TABLE magazine DROP ap_indexable');
    }
}
