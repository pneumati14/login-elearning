<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A card order that reaches the received status lands on the billing
 * board right away. The billing item keeps a link to its source order
 * (duplicate guard) plus the card's type label for display. Backfills
 * items for orders already received.
 */
final class Version20260605110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billing items from received card orders; backfill existing ones.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_item ADD card_order_id INTEGER NULL REFERENCES customer_card_order(id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE billing_item ADD card_name VARCHAR(255) NULL');
        $this->addSql('CREATE INDEX idx_billing_item_card_order ON billing_item (card_order_id)');

        // One billing row per already-received order: snapshotted product,
        // quantity and sale price, dated today (the receive date is unknown).
        $this->addSql(<<<'SQL'
            INSERT INTO billing_item (customer_id, card_order_id, card_name, name, quantity, unit_price, currency, status, won_at, created_at)
            SELECT cc.customer_id, o.id, cc.type, o.product_name, o.quantity, COALESCE(o.unit_sale_price, 0), o.currency, 'pending', CURRENT_DATE, NOW()
            FROM customer_card_order o
            JOIN customer_card cc ON cc.id = o.card_id
            WHERE o.status = 'received'
            ORDER BY o.id
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_billing_item_card_order');
        $this->addSql('ALTER TABLE billing_item DROP card_order_id');
        $this->addSql('ALTER TABLE billing_item DROP card_name');
    }
}
