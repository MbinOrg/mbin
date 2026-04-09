<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311182316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'support reporting messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ALTER uuid DROP DEFAULT');
        $this->addSql('ALTER TABLE message_thread ALTER updated_at DROP NOT NULL');

        $this->addSql('ALTER TABLE report ADD message_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE report ALTER magazine_id DROP NOT NULL');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C42F7784537A1329 ON report (message_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread ALTER updated_at SET NOT NULL');
        $this->addSql('ALTER TABLE message ALTER uuid SET DEFAULT \'gen_random_uuid()\'');

        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784537A1329');
        $this->addSql('DROP INDEX IDX_C42F7784537A1329');
        $this->addSql('ALTER TABLE report DROP message_id');
        $this->addSql('ALTER TABLE report ALTER magazine_id SET NOT NULL');
    }
}
