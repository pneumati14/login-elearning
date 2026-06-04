<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Opportunity stages gain a win probability (%) used to weight deal
 * values in the pipeline forecast. Terminal stages are fixed (won = 100,
 * lost = 0); existing open stages get an even ascending ramp by position
 * within their pipeline as a starting point, to be tuned by the admin.
 */
final class Version20260604100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpportunityStage: add probability (%) for forecast weighting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity_stage ADD COLUMN probability INTEGER NOT NULL DEFAULT 0');
        $this->addSql("UPDATE opportunity_stage SET probability = 100 WHERE outcome = 'won'");
        // Open stages: even ramp, e.g. 3 open stages -> 25 / 50 / 75.
        $this->addSql(<<<'SQL'
            UPDATE opportunity_stage s
            SET probability = sub.p
            FROM (
                SELECT id,
                       ROUND(100.0 * ROW_NUMBER() OVER (PARTITION BY type_id ORDER BY position, id)
                             / (COUNT(*) OVER (PARTITION BY type_id) + 1)) AS p
                FROM opportunity_stage
                WHERE outcome = 'open'
            ) sub
            WHERE s.id = sub.id
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity_stage DROP COLUMN probability');
    }
}
