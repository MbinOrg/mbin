<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260113151625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set ap_discoverable to true for all local actors. In the past this was not used and only populated for remote actors.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE "user" SET ap_discoverable = true WHERE ap_id IS NULL');
        $this->addSql('UPDATE magazine SET ap_discoverable = true WHERE ap_id IS NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
