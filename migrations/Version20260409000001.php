<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar_profile table for persisting SL profile data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE avatar_profile (
                avatar_key VARCHAR(36) NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                image_url TEXT DEFAULT NULL,
                bio_html MEDIUMTEXT DEFAULT NULL,
                synced_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(avatar_key)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE avatar_profile');
    }
}
