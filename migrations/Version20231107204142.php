<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20231107204142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add column remote_followers_count';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD ap_followers_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE magazine ADD ap_followers_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD ap_attributed_to_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE magazine ADD ap_attributed_to_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP ap_followers_count');
        $this->addSql('ALTER TABLE magazine DROP ap_followers_count');
        $this->addSql('ALTER TABLE "user" DROP ap_attributed_to_url');
        $this->addSql('ALTER TABLE magazine DROP ap_attributed_to_url');
    }
}
