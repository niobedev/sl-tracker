<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: event, sync_state, user tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE event (
                id INT AUTO_INCREMENT NOT NULL,
                event_ts DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                action VARCHAR(10) NOT NULL,
                avatar_key VARCHAR(36) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                username VARCHAR(100) NOT NULL,
                INDEX idx_event_ts (event_ts),
                INDEX idx_avatar_key (avatar_key),
                INDEX idx_action_ts (action, event_ts),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE sync_state (
                id INT AUTO_INCREMENT NOT NULL,
                last_row INT NOT NULL,
                synced_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                rows_synced INT NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE `user` (
                id INT AUTO_INCREMENT NOT NULL,
                username VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE sync_state');
        $this->addSql('DROP TABLE `user`');
    }
}
