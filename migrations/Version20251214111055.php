<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251214111055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modify UserPushSubscription, so that the user is not nullable and it cascade deletes if the user is deleted';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_push_subscription DROP CONSTRAINT FK_AE378BD8A76ED395');
        $this->addSql('ALTER TABLE user_push_subscription ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE user_push_subscription ADD CONSTRAINT FK_AE378BD8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_push_subscription DROP CONSTRAINT fk_ae378bd8a76ed395');
        $this->addSql('ALTER TABLE user_push_subscription ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE user_push_subscription ADD CONSTRAINT fk_ae378bd8a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
