<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add opportunity_type and opportunity_stage tables (CRM phase 3,
 * step A — the configurable pipeline engine). Seeds a default
 * "Általános értékesítés" type with six standard stages so the
 * feature is usable out of the box.
 */
final class Version20260603140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add opportunity_type + opportunity_stage; seed a default pipeline.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE opportunity_type (
                id          SERIAL PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                position    INTEGER      NOT NULL DEFAULT 0,
                is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE opportunity_stage (
                id        SERIAL PRIMARY KEY,
                type_id   INTEGER      NOT NULL REFERENCES opportunity_type(id) ON DELETE CASCADE,
                name      VARCHAR(255) NOT NULL,
                position  INTEGER      NOT NULL DEFAULT 0,
                outcome   VARCHAR(16)  NOT NULL DEFAULT 'open'
            )
        SQL);

        $this->addSql('CREATE INDEX idx_opportunity_stage_type ON opportunity_stage (type_id)');

        // ── Seed the default pipeline ────────────────────────────────
        $this->addSql(<<<'SQL'
            INSERT INTO opportunity_type (name, position, is_active, created_at, updated_at)
            VALUES ('Általános értékesítés', 0, TRUE, NOW(), NOW())
        SQL);

        $stages = [
            ['Érdeklődő', 0, 'open'],
            ['Minősített', 1, 'open'],
            ['Ajánlat', 2, 'open'],
            ['Tárgyalás', 3, 'open'],
            ['Megnyert', 4, 'won'],
            ['Elvesztett', 5, 'lost'],
        ];
        foreach ($stages as [$name, $position, $outcome]) {
            $this->addSql(
                'INSERT INTO opportunity_stage (type_id, name, position, outcome)
                 SELECT id, :name, :position, :outcome FROM opportunity_type WHERE name = :type_name',
                [
                    'name' => $name,
                    'position' => $position,
                    'outcome' => $outcome,
                    'type_name' => 'Általános értékesítés',
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE opportunity_stage');
        $this->addSql('DROP TABLE opportunity_type');
    }
}
