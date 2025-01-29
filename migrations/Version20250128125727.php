<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250128125727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the default sort columns for front and comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TYPE enumSortOptions AS ENUM(\'hot\', \'top\', \'newest\', \'active\', \'oldest\', \'commented\')');
        $this->addSql('ALTER TABLE "user" ADD front_default_sort enumSortOptions DEFAULT \'hot\' NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD comment_default_sort enumSortOptions DEFAULT \'hot\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP front_default_sort');
        $this->addSql('ALTER TABLE "user" DROP comment_default_sort');
        $this->addSql('DROP TYPE enumSortOptions');
    }
}
