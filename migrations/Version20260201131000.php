<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the name of local magazines to their tags array';
    }

    public function up(Schema $schema): void
    {
        // this will not match where tags IS NULL
        $this->addSql('UPDATE magazine SET tags = tags || jsonb_build_array(name) WHERE ap_id IS NULL AND NOT (tags @> jsonb_build_array(name));');
        // set it where tags IS NULL
        $this->addSql('UPDATE magazine SET tags = jsonb_build_array(name) WHERE ap_id IS NULL AND tags IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE magazine SET tags = tags - name WHERE ap_id IS NULL;');
    }
}
