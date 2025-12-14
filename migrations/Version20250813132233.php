<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250813132233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_banned and is_explicitly_allowed to the instance table and move the banned instances out of the settings table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE instance ADD IF NOT EXISTS is_banned BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE instance ADD IF NOT EXISTS is_explicitly_allowed BOOLEAN DEFAULT false NOT NULL');

        $this->addSql("DO
\$do\$
    declare tempRow record;
BEGIN
    FOR tempRow IN
        SELECT keys.value FROM settings s
            JOIN LATERAL (SELECT * FROM jsonb_array_elements_text(s.json)) as keys ON TRUE
            WHERE s.name = 'KBIN_BANNED_INSTANCES'
    LOOP
        IF NOT EXISTS (SELECT * FROM instance i WHERE i.domain = tempRow.value) THEN
            INSERT INTO instance(id, domain, created_at, failed_delivers, updated_at, is_banned)
                VALUES (NEXTVAL('instance_id_seq'), tempRow.value, current_timestamp(0), 0, current_timestamp(0), true);
        ELSE
            UPDATE instance SET is_banned = true WHERE domain = tempRow.value;
        END IF;
    END LOOP;
END
\$do\$;");

        $this->addSql('DELETE FROM settings WHERE name=\'KBIN_BANNED_INSTANCES\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DO
\$do\$
    declare tempRow record;
BEGIN
    FOR tempRow IN
        SELECT i.domain FROM instance i WHERE i.is_banned = true
    LOOP
        IF NOT EXISTS (SELECT * FROM settings s WHERE s.name = 'KBIN_BANNED_INSTANCES') THEN
            INSERT INTO settings (id, name, json) VALUES (NEXTVAL('settings_id_seq'), 'KBIN_BANNED_INSTANCES', '[]'::jsonb);
        END IF;
        UPDATE settings SET json = json || to_jsonb(tempRow.domain) WHERE name = 'KBIN_BANNED_INSTANCES';
    END LOOP;
END
\$do\$;");
        $this->addSql('ALTER TABLE instance DROP is_banned');
        $this->addSql('ALTER TABLE instance DROP is_explicitly_allowed');
    }
}
