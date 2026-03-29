<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303142852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add local_size, original_size, is_compressed and source_too_big image columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ADD is_compressed BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE image ADD source_too_big BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE image ADD downloaded_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        // init the column for all existing images, it gets overwritten in the big loop underneath
        $this->addSql('ALTER TABLE image ADD created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT current_timestamp');
        $this->addSql('ALTER TABLE image ALTER created_at DROP DEFAULT;');
        $this->addSql('ALTER TABLE image ADD original_size BIGINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE image ADD local_size BIGINT DEFAULT 0 NOT NULL');

        // set the downloaded at value to something realistically
        $this->addSql('DO
$do$
    declare tempRow record;
BEGIN
    FOR tempRow IN
        SELECT i.id, e.created_at as ec, ec.created_at as ecc, p.created_at as pc, pc.created_at as pcc, u.created_at as uc, u2.created_at as u2c, m.created_at as mc, m2.created_at as m2c FROM image i
            LEFT JOIN entry e ON i.id = e.image_id
            LEFT JOIN entry_comment ec ON i.id = ec.image_id
            LEFT JOIN post p ON i.id = p.image_id
            LEFT JOIN post_comment pc ON i.id = pc.image_id
            LEFT JOIN "user" u ON i.id = u.avatar_id
            LEFT JOIN "user" u2 ON i.id = u2.cover_id
            LEFT JOIN magazine m ON i.id = m.icon_id
            LEFT JOIN magazine m2 ON i.id = m2.banner_id
    LOOP
        IF tempRow.ec IS NOT NULL THEN
            UPDATE image SET downloaded_at = tempRow.ec, created_at = tempRow.ec WHERE id = tempRow.id;
        ELSIF tempRow.ecc IS NOT NULL THEN
            UPDATE image SET downloaded_at = tempRow.ecc, created_at = tempRow.ecc WHERE id = tempRow.id;
        ELSIF tempRow.pc IS NOT NULL THEN
            UPDATE image SET downloaded_at = tempRow.pc, created_at = tempRow.pc WHERE id = tempRow.id;
        ELSIF tempRow.pcc IS NOT NULL THEN
            UPDATE image SET downloaded_at = tempRow.pcc, created_at = tempRow.pcc WHERE id = tempRow.id;
        ELSIF tempRow.uc IS NOT NULL THEN
            UPDATE image SET downloaded_at = tempRow.uc, created_at = tempRow.uc WHERE id = tempRow.id;
        ELSIF tempRow.u2c IS NOT NULL THEN
            UPDATE image SET downloaded_at = tempRow.u2c, created_at = tempRow.u2c WHERE id = tempRow.id;
        ELSIF tempRow.mc IS NOT NULL THEN
            UPDATE image SET downloaded_at = tempRow.mc, created_at = tempRow.mc WHERE id = tempRow.id;
        ELSIF tempRow.m2c IS NOT NULL THEN
            UPDATE image SET downloaded_at = tempRow.m2c, created_at = tempRow.m2c WHERE id = tempRow.id;
        END IF;
    END LOOP;
END
$do$;');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP is_compressed');
        $this->addSql('ALTER TABLE image DROP source_too_big');
        $this->addSql('ALTER TABLE image DROP downloaded_at');
        $this->addSql('ALTER TABLE image DROP created_at');
        $this->addSql('ALTER TABLE image DROP original_size');
        $this->addSql('ALTER TABLE image DROP local_size');
    }
}
