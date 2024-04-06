<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240402190028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make the foreign keys in the message, message_thread and message_thread_participants tables cascade delete';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread_participants DROP CONSTRAINT FK_F2DE92908829462F');
        $this->addSql('ALTER TABLE message_thread_participants DROP CONSTRAINT FK_F2DE9290A76ED395');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307FE2904019');
        $this->addSql('ALTER TABLE message_thread_participants ADD CONSTRAINT FK_F2DE92908829462F FOREIGN KEY (message_thread_id) REFERENCES message_thread (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE message_thread_participants ADD CONSTRAINT FK_F2DE9290A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FE2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP CONSTRAINT fk_b6bd307fe2904019');
        $this->addSql('ALTER TABLE message_thread_participants DROP CONSTRAINT fk_f2de92908829462f');
        $this->addSql('ALTER TABLE message_thread_participants DROP CONSTRAINT fk_f2de9290a76ed395');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT fk_b6bd307fe2904019 FOREIGN KEY (thread_id) REFERENCES message_thread (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE message_thread_participants ADD CONSTRAINT fk_f2de92908829462f FOREIGN KEY (message_thread_id) REFERENCES message_thread (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE message_thread_participants ADD CONSTRAINT fk_f2de9290a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
