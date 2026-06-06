<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Opportunity nature: new business vs. upsell at an existing customer
 * (manually set on the form, filterable on the pipeline report).
 * Existing rows get a one-off smart backfill: an opportunity counts as
 * an upsell when its customer already had a deal WON before this one
 * was created; everything else starts as "new".
 */
final class Version20260606110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Opportunity: add nature (new/upsell) with smart backfill.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE opportunity ADD nature VARCHAR(16) NOT NULL DEFAULT 'new'");
        $this->addSql(<<<'SQL'
            UPDATE opportunity o SET nature = 'upsell'
            WHERE EXISTS (
                SELECT 1
                FROM opportunity o2
                JOIN opportunity_stage s2 ON s2.id = o2.stage_id
                WHERE o2.customer_id = o.customer_id
                  AND o2.id <> o.id
                  AND s2.outcome = 'won'
                  AND o2.closed_at IS NOT NULL
                  AND o2.closed_at <= o.created_at
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity DROP COLUMN nature');
    }
}
