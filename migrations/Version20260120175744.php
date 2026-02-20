<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260120175744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add initial monitoring tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE monitoring_curl_request_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE monitoring_query_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE monitoring_twig_render_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE monitoring_curl_request (id INT NOT NULL, context_id UUID DEFAULT NULL, url VARCHAR(255) NOT NULL, method VARCHAR(255) NOT NULL, was_successful BOOLEAN NOT NULL, exception VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at_microseconds DOUBLE PRECISION NOT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ended_at_microseconds DOUBLE PRECISION NOT NULL, duration_milliseconds DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_19A4B8546B00C1CF ON monitoring_curl_request (context_id)');
        $this->addSql('CREATE TABLE monitoring_execution_context (uuid UUID NOT NULL, execution_type VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, handler VARCHAR(255) NOT NULL, user_type VARCHAR(255) NOT NULL, status_code INT DEFAULT NULL, exception VARCHAR(255) DEFAULT NULL, stacktrace VARCHAR(255) DEFAULT NULL, response_sending_duration_milliseconds DOUBLE PRECISION DEFAULT NULL, query_duration_milliseconds DOUBLE PRECISION NOT NULL, twig_render_duration_milliseconds DOUBLE PRECISION NOT NULL, curl_request_duration_milliseconds DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at_microseconds DOUBLE PRECISION NOT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ended_at_microseconds DOUBLE PRECISION NOT NULL, duration_milliseconds DOUBLE PRECISION NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE monitoring_query (id INT NOT NULL, context_id UUID DEFAULT NULL, query_string_id VARCHAR(40) DEFAULT NULL, parameters JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at_microseconds DOUBLE PRECISION NOT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ended_at_microseconds DOUBLE PRECISION NOT NULL, duration_milliseconds DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_760D8AF36B00C1CF ON monitoring_query (context_id)');
        $this->addSql('CREATE INDEX IDX_760D8AF3BCAEFD40 ON monitoring_query (query_string_id)');
        $this->addSql('CREATE TABLE monitoring_query_string (query_hash VARCHAR(40) NOT NULL, query TEXT NOT NULL, PRIMARY KEY(query_hash))');
        $this->addSql('CREATE TABLE monitoring_twig_render (id INT NOT NULL, context_id UUID DEFAULT NULL, parent_id INT DEFAULT NULL, short_description TEXT NOT NULL, template_name VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, memory_usage INT DEFAULT NULL, peak_memory_usage INT DEFAULT NULL, profiler_duration DOUBLE PRECISION DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at_microseconds DOUBLE PRECISION NOT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ended_at_microseconds DOUBLE PRECISION NOT NULL, duration_milliseconds DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_55BA2A536B00C1CF ON monitoring_twig_render (context_id)');
        $this->addSql('CREATE INDEX IDX_55BA2A53727ACA70 ON monitoring_twig_render (parent_id)');
        $this->addSql('ALTER TABLE monitoring_curl_request ADD CONSTRAINT FK_19A4B8546B00C1CF FOREIGN KEY (context_id) REFERENCES monitoring_execution_context (uuid) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE monitoring_query ADD CONSTRAINT FK_760D8AF36B00C1CF FOREIGN KEY (context_id) REFERENCES monitoring_execution_context (uuid) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE monitoring_query ADD CONSTRAINT FK_760D8AF3BCAEFD40 FOREIGN KEY (query_string_id) REFERENCES monitoring_query_string (query_hash) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE monitoring_twig_render ADD CONSTRAINT FK_55BA2A536B00C1CF FOREIGN KEY (context_id) REFERENCES monitoring_execution_context (uuid) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE monitoring_twig_render ADD CONSTRAINT FK_55BA2A53727ACA70 FOREIGN KEY (parent_id) REFERENCES monitoring_twig_render (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE monitoring_curl_request_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE monitoring_query_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE monitoring_twig_render_id_seq CASCADE');
        $this->addSql('ALTER TABLE monitoring_curl_request DROP CONSTRAINT FK_19A4B8546B00C1CF');
        $this->addSql('ALTER TABLE monitoring_query DROP CONSTRAINT FK_760D8AF36B00C1CF');
        $this->addSql('ALTER TABLE monitoring_query DROP CONSTRAINT FK_760D8AF3BCAEFD40');
        $this->addSql('ALTER TABLE monitoring_twig_render DROP CONSTRAINT FK_55BA2A536B00C1CF');
        $this->addSql('ALTER TABLE monitoring_twig_render DROP CONSTRAINT FK_55BA2A53727ACA70');
        $this->addSql('DROP TABLE monitoring_curl_request');
        $this->addSql('DROP TABLE monitoring_execution_context');
        $this->addSql('DROP TABLE monitoring_query');
        $this->addSql('DROP TABLE monitoring_query_string');
        $this->addSql('DROP TABLE monitoring_twig_render');
    }
}
