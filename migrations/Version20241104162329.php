<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241104162655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add application_text and is_approved to the user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD application_text VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD is_approved BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD is_rejected BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP application_text');
        $this->addSql('ALTER TABLE "user" DROP is_approved');
        $this->addSql('ALTER TABLE "user" DROP is_rejected');
    }
}
