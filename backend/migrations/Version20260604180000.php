<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fee items can reference a catalogue product. The name/amount stay on
 * the item (prefilled from the product, freely overridable), so the fee
 * survives catalogue edits — the FK is only a reference (SET NULL).
 */
final class Version20260604180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CustomerFeeItem: optional product reference.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_fee_item ADD COLUMN product_id INTEGER NULL REFERENCES product(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_customer_fee_item_product ON customer_fee_item (product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_customer_fee_item_product');
        $this->addSql('ALTER TABLE customer_fee_item DROP COLUMN product_id');
    }
}
