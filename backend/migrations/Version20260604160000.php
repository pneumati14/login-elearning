<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Itemised monthly fees: the single customer.monthly_fee field is
 * replaced by customer_fee_item rows with validity periods, so price
 * changes keep history and the current fee is a computed sum. Any
 * already-entered single fee is carried over as one open-ended item.
 */
final class Version20260604160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer: replace the single monthly fee with validity-scoped fee items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_fee_item (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                amount NUMERIC(14, 2) NOT NULL,
                currency VARCHAR(3) NOT NULL,
                valid_from DATE NULL,
                valid_until DATE NULL,
                notes TEXT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_customer_fee_item_customer ON customer_fee_item (customer_id)');
        // Preserve fees entered while the field was a single amount.
        $this->addSql(<<<'SQL'
            INSERT INTO customer_fee_item (customer_id, name, amount, currency, created_at)
            SELECT id, 'Havidíj', monthly_fee, monthly_fee_currency, NOW()
            FROM customer
            WHERE monthly_fee IS NOT NULL
            SQL);
        $this->addSql('ALTER TABLE customer DROP COLUMN monthly_fee');
        $this->addSql('ALTER TABLE customer DROP COLUMN monthly_fee_currency');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD COLUMN monthly_fee NUMERIC(14, 2) NULL');
        $this->addSql("ALTER TABLE customer ADD COLUMN monthly_fee_currency VARCHAR(3) NOT NULL DEFAULT 'HUF'");
        $this->addSql('DROP TABLE customer_fee_item');
    }
}
