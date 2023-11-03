<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20231103004800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change the scoring of entry and post tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE entry SET score=favourite_count + up_votes - down_votes');
        $this->addSql('UPDATE post SET score=favourite_count + up_votes - down_votes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE entry SET score=up_votes - down_votes');
        $this->addSql('UPDATE post SET score=up_votes - down_votes');
    }
}