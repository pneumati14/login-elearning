<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Headcount-based fee items: a per-head item stores a unit price and a
 * headcount, and its effective amount is the product of the two. Flat
 * items keep using the amount directly.
 */
final class Version20260604200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CustomerFeeItem: headcount-based pricing (unit price × headcount).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_fee_item ADD COLUMN is_per_head BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE customer_fee_item ADD COLUMN unit_amount NUMERIC(14, 2) NULL');
        $this->addSql('ALTER TABLE customer_fee_item ADD COLUMN quantity INTEGER NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_fee_item DROP COLUMN quantity');
        $this->addSql('ALTER TABLE customer_fee_item DROP COLUMN unit_amount');
        $this->addSql('ALTER TABLE customer_fee_item DROP COLUMN is_per_head');
    }
}
