<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910011700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tune autovacuum thresholds for high-churn content tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE favourite SET (
            autovacuum_vacuum_threshold = 50000,
            autovacuum_vacuum_scale_factor = 0.005,
            autovacuum_analyze_threshold = 50000,
            autovacuum_analyze_scale_factor = 0.005
        )');
        $this->addSql('ALTER TABLE entry_comment SET (
            autovacuum_vacuum_threshold = 20000,
            autovacuum_vacuum_scale_factor = 0.02,
            autovacuum_analyze_threshold = 20000,
            autovacuum_analyze_scale_factor = 0.02
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE favourite RESET (
            autovacuum_vacuum_threshold,
            autovacuum_vacuum_scale_factor,
            autovacuum_analyze_threshold,
            autovacuum_analyze_scale_factor
        )');
        $this->addSql('ALTER TABLE entry_comment RESET (
            autovacuum_vacuum_threshold,
            autovacuum_vacuum_scale_factor,
            autovacuum_analyze_threshold,
            autovacuum_analyze_scale_factor
        )');
    }
}
