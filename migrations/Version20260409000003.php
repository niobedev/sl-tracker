<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convert all existing datetimes from Europe/Kyiv to UTC.
 *
 * PREREQUISITE: MySQL timezone tables must be populated before running this migration.
 * On Docker, run once on the MySQL container:
 *   docker exec <mysql_container> sh -c \
 *     "mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -uroot -p<password> mysql"
 *
 * DEPLOYMENT ORDER: Run this migration BEFORE deploying code that sets date_default_timezone_set('UTC').
 */
final class Version20260409000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert event.event_ts, sync_state.synced_at, avatar_profile.synced_at from Europe/Kyiv to UTC';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE event SET event_ts = CONVERT_TZ(event_ts, 'Europe/Kyiv', 'UTC')");
        $this->addSql("UPDATE sync_state SET synced_at = CONVERT_TZ(synced_at, 'Europe/Kyiv', 'UTC') WHERE synced_at IS NOT NULL");
        $this->addSql("UPDATE avatar_profile SET synced_at = CONVERT_TZ(synced_at, 'Europe/Kyiv', 'UTC')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE event SET event_ts = CONVERT_TZ(event_ts, 'UTC', 'Europe/Kyiv')");
        $this->addSql("UPDATE sync_state SET synced_at = CONVERT_TZ(synced_at, 'UTC', 'Europe/Kyiv') WHERE synced_at IS NOT NULL");
        $this->addSql("UPDATE avatar_profile SET synced_at = CONVERT_TZ(synced_at, 'UTC', 'Europe/Kyiv')");
    }
}
