<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_data MEDIUMBLOB to avatar_profile for local image caching';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avatar_profile ADD COLUMN image_data MEDIUMBLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avatar_profile DROP COLUMN image_data');
    }
}
