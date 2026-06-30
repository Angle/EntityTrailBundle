<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000000_AngleEntityTrailLog extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create entity_trail_logs table for anglemx/entity-trail-bundle.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS entity_trail_logs (
                id          INT UNSIGNED AUTO_INCREMENT NOT NULL,
                code        VARCHAR(32) NOT NULL,
                entity_type VARCHAR(255) NOT NULL,
                entity_id   INT UNSIGNED NOT NULL,
                entity_code VARCHAR(32) NULL,
                action      VARCHAR(16) NOT NULL,
                changes     JSON NOT NULL,
                user_id     INT UNSIGNED NULL,
                user_label  VARCHAR(255) NULL,
                ip_address  VARCHAR(45) NULL,
                created_at  DATETIME NOT NULL,
                UNIQUE INDEX uniq_entity_trail_code (code),
                INDEX idx_entity_trail_entity (entity_type, entity_id, created_at),
                INDEX idx_entity_trail_created (created_at),
                INDEX idx_entity_trail_user (user_id, created_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS entity_trail_logs');
    }
}
