<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop avatar_note and avatar_reminder tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS avatar_note');
        $this->addSql('DROP TABLE IF EXISTS avatar_reminder');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE avatar_note (
                id INT AUTO_INCREMENT NOT NULL,
                avatar_key VARCHAR(36) NOT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                author_id INT NOT NULL,
                INDEX idx_note_avatar_key (avatar_key),
                INDEX idx_note_author (author_id),
                PRIMARY KEY(id),
                CONSTRAINT fk_note_author FOREIGN KEY (author_id)
                    REFERENCES `user`(id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE avatar_reminder (
                id INT AUTO_INCREMENT NOT NULL,
                avatar_key VARCHAR(36) NOT NULL,
                content LONGTEXT NOT NULL,
                reminder_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                author_id INT NOT NULL,
                resolved_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                resolved_by_id INT DEFAULT NULL,
                INDEX idx_reminder_avatar_key (avatar_key),
                INDEX idx_reminder_resolved (resolved_at),
                INDEX idx_reminder_author (author_id),
                PRIMARY KEY(id),
                CONSTRAINT fk_reminder_author FOREIGN KEY (author_id)
                    REFERENCES `user`(id) ON DELETE CASCADE,
                CONSTRAINT fk_reminder_resolved_by FOREIGN KEY (resolved_by_id)
                    REFERENCES `user`(id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }
}