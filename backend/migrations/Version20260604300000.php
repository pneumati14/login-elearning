<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Billing items: itemised rows to invoice, snapshotted automatically from
 * a deal's quote lines when it is won (a lineless deal becomes one row
 * from its title and value). Backfills the rows for already-won deals so
 * the new billing table starts populated.
 */
final class Version20260604300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billing items table; backfill from already-won opportunities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE billing_item (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                opportunity_id INTEGER NULL REFERENCES opportunity(id) ON DELETE SET NULL,
                opportunity_title VARCHAR(255) NULL,
                name VARCHAR(255) NOT NULL,
                quantity NUMERIC(12, 2) NOT NULL,
                unit_price NUMERIC(14, 2) NOT NULL,
                currency VARCHAR(3) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                won_at DATE NULL,
                invoiced_at DATE NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_billing_item_customer ON billing_item (customer_id)');
        $this->addSql('CREATE INDEX idx_billing_item_opportunity ON billing_item (opportunity_id)');

        // Backfill: one billing row per quote line of every won deal…
        $this->addSql(<<<'SQL'
            INSERT INTO billing_item (customer_id, opportunity_id, opportunity_title, name, quantity, unit_price, currency, status, won_at, created_at)
            SELECT o.customer_id, o.id, o.title, li.product_name, li.quantity, li.unit_price, o.currency, 'pending', o.closed_at, NOW()
            FROM opportunity_line_item li
            JOIN opportunity o ON o.id = li.opportunity_id
            JOIN opportunity_stage s ON s.id = o.stage_id
            WHERE s.outcome = 'won'
            ORDER BY o.id, li.position, li.id
            SQL);
        // …and one row from the title/value of won deals without lines.
        $this->addSql(<<<'SQL'
            INSERT INTO billing_item (customer_id, opportunity_id, opportunity_title, name, quantity, unit_price, currency, status, won_at, created_at)
            SELECT o.customer_id, o.id, o.title, o.title, 1, COALESCE(o.value, 0), o.currency, 'pending', o.closed_at, NOW()
            FROM opportunity o
            JOIN opportunity_stage s ON s.id = o.stage_id
            WHERE s.outcome = 'won'
              AND NOT EXISTS (SELECT 1 FROM opportunity_line_item li WHERE li.opportunity_id = o.id)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE billing_item');
    }
}
