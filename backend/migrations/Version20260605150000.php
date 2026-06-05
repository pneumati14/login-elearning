<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PO handling on the billing tab: a "works with purchase orders" flag
 * on the customer and a PO-number history with validity periods.
 */
final class Version20260605150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer PO flag + PO-number history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD has_po BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_po_number (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                po_number VARCHAR(255) NOT NULL,
                valid_from DATE NULL,
                valid_until DATE NULL,
                notes TEXT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_customer_po_number_customer ON customer_po_number (customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_po_number');
        $this->addSql('ALTER TABLE customer DROP has_po');
    }
}
