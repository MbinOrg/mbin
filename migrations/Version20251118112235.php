<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251118112235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add column front_default_content to "user"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TYPE enumFrontContentOptions AS ENUM(\'all\', \'threads\', \'microblog\')');
        $this->addSql('ALTER TABLE "user" ADD front_default_content enumFrontContentOptions DEFAULT \'all\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP front_default_content');
        $this->addSql('DROP TYPE enumFrontContentOptions');
    }
}
