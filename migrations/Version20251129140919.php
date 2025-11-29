<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251129140919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename column to combined in front_default_content enum. But there is no rename of enum values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP front_default_content');
        $this->addSql('DROP TYPE enumFrontContentOptions');
        $this->addSql('CREATE TYPE enumfrontcontentoptions AS ENUM (\'combined\', \'threads\', \'microblog\')');
        $this->addSql('ALTER TABLE "user" ADD front_default_content enumFrontContentOptions DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP front_default_content');
        $this->addSql('DROP TYPE enumFrontContentOptions');
        $this->addSql('CREATE TYPE enumfrontcontentoptions AS ENUM (\'all\', \'threads\', \'microblog\')');
        $this->addSql('ALTER TABLE "user" ADD front_default_content enumFrontContentOptions DEFAULT NULL');
    }
}
