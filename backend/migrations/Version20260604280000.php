<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fulfillment board for won deals: admin-managed categories (seeded:
 * Hardver, Szoftver, Fejlesztési projekt) with ordered delivery stages
 * ("done" flag on terminal ones), and the category/stage references on
 * the opportunity. Won deals appear on the board automatically; the
 * category is assigned there.
 */
final class Version20260604280000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fulfillment types + stages; fulfillment refs on opportunity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE fulfillment_type (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                position INTEGER NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE fulfillment_stage (
                id SERIAL PRIMARY KEY,
                type_id INTEGER NOT NULL REFERENCES fulfillment_type(id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                position INTEGER NOT NULL,
                is_done BOOLEAN NOT NULL DEFAULT FALSE
            )
            SQL);
        $this->addSql('CREATE INDEX idx_fulfillment_stage_type ON fulfillment_stage (type_id)');

        $this->addSql('ALTER TABLE opportunity ADD COLUMN fulfillment_type_id INTEGER NULL REFERENCES fulfillment_type(id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE opportunity ADD COLUMN fulfillment_stage_id INTEGER NULL REFERENCES fulfillment_stage(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_opportunity_fulfillment_stage ON opportunity (fulfillment_stage_id)');

        // Seed the three default categories with a sensible starter process.
        $this->addSql(<<<'SQL'
            INSERT INTO fulfillment_type (name, position, created_at, updated_at) VALUES
            ('Hardver', 0, NOW(), NOW()),
            ('Szoftver', 1, NOW(), NOW()),
            ('Fejlesztési projekt', 2, NOW(), NOW())
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO fulfillment_stage (type_id, name, position, is_done)
            SELECT t.id, s.name, s.position, s.is_done
            FROM fulfillment_type t
            JOIN (VALUES
                ('Hardver', 'Előkészítés', 0, FALSE),
                ('Hardver', 'Beszerzés', 1, FALSE),
                ('Hardver', 'Telepítés', 2, FALSE),
                ('Hardver', 'Átadva', 3, TRUE),
                ('Szoftver', 'Előkészítés', 0, FALSE),
                ('Szoftver', 'Konfiguráció', 1, FALSE),
                ('Szoftver', 'Oktatás', 2, FALSE),
                ('Szoftver', 'Átadva', 3, TRUE),
                ('Fejlesztési projekt', 'Specifikáció', 0, FALSE),
                ('Fejlesztési projekt', 'Fejlesztés', 1, FALSE),
                ('Fejlesztési projekt', 'Tesztelés', 2, FALSE),
                ('Fejlesztési projekt', 'Átadva', 3, TRUE)
            ) AS s(type_name, name, position, is_done) ON s.type_name = t.name
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_opportunity_fulfillment_stage');
        $this->addSql('ALTER TABLE opportunity DROP COLUMN fulfillment_stage_id');
        $this->addSql('ALTER TABLE opportunity DROP COLUMN fulfillment_type_id');
        $this->addSql('DROP TABLE fulfillment_stage');
        $this->addSql('DROP TABLE fulfillment_type');
    }
}
