<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_channel and tracked_avatar tables, update event table, drop sync_state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE notification_channel (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                config JSON NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                INDEX idx_channel_type (type),
                INDEX idx_channel_enabled (enabled)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE tracked_avatar (
                id INT AUTO_INCREMENT NOT NULL,
                avatar_key VARCHAR(36) NOT NULL,
                tracking_enabled TINYINT(1) NOT NULL DEFAULT 1,
                notification_channel_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_6A8330C5F85E0677 (avatar_key),
                INDEX idx_tracked_avatar_enabled (tracking_enabled),
                PRIMARY KEY(id),
                CONSTRAINT fk_tracked_avatar_profile FOREIGN KEY (avatar_key)
                    REFERENCES avatar_profile(avatar_key) ON DELETE CASCADE,
                CONSTRAINT fk_tracked_avatar_channel FOREIGN KEY (notification_channel_id)
                    REFERENCES notification_channel(id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<SQL
            ALTER TABLE event
            ADD COLUMN region_name VARCHAR(255) DEFAULT NULL,
            ADD COLUMN position TEXT DEFAULT NULL,
            ADD INDEX idx_event_region (region_name)
        SQL);

        $this->addSql('TRUNCATE TABLE event');

        $this->addSql('DROP TABLE sync_state');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tracked_avatar');
        $this->addSql('DROP TABLE notification_channel');
        $this->addSql('ALTER TABLE event DROP COLUMN region_name, DROP COLUMN position');
        $this->addSql(<<<SQL
            CREATE TABLE sync_state (
                id INT AUTO_INCREMENT NOT NULL,
                last_row INT NOT NULL,
                synced_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                rows_synced INT NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }
}
