<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Card orders carry per-piece purchase and sale prices, so the system
 * can compute order totals and the margin (sale − purchase).
 */
final class Version20260604260000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CustomerCardOrder: per-piece purchase/sale prices + currency.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_card_order ADD COLUMN unit_purchase_price NUMERIC(14, 2) NULL');
        $this->addSql('ALTER TABLE customer_card_order ADD COLUMN unit_sale_price NUMERIC(14, 2) NULL');
        $this->addSql("ALTER TABLE customer_card_order ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'HUF'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_card_order DROP COLUMN currency');
        $this->addSql('ALTER TABLE customer_card_order DROP COLUMN unit_sale_price');
        $this->addSql('ALTER TABLE customer_card_order DROP COLUMN unit_purchase_price');
    }
}
