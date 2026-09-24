<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726182822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add HashtagSubscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE hashtag_subscription_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE hashtag_subscription (id INT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id INT NOT NULL, hashtag_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5814F278A76ED395 ON hashtag_subscription (user_id)');
        $this->addSql('CREATE INDEX IDX_5814F278FB34EF56 ON hashtag_subscription (hashtag_id)');
        $this->addSql('CREATE UNIQUE INDEX hashtag_subscription_idx ON hashtag_subscription (user_id, hashtag_id)');
        $this->addSql('ALTER TABLE hashtag_subscription ADD CONSTRAINT FK_5814F278A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE hashtag_subscription ADD CONSTRAINT FK_5814F278FB34EF56 FOREIGN KEY (hashtag_id) REFERENCES hashtag (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "user" ADD show_comments_of_subscribed_hashtags BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE hashtag_subscription_id_seq CASCADE');
        $this->addSql('ALTER TABLE hashtag_subscription DROP CONSTRAINT FK_5814F278A76ED395');
        $this->addSql('ALTER TABLE hashtag_subscription DROP CONSTRAINT FK_5814F278FB34EF56');
        $this->addSql('DROP TABLE hashtag_subscription');
        $this->addSql('ALTER TABLE "user" DROP show_comments_of_subscribed_hashtags');
    }
}
