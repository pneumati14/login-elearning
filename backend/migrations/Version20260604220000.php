<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Customer cards: an admin-managed supplier master list, per-customer
 * cards (free-text type + uniqueness, optional supplier) and the orders
 * placed for each card (catalogue product with name snapshot, mandatory
 * quantity, order date + status).
 */
final class Version20260604220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppliers + customer cards with product orders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                contact_name VARCHAR(255) NULL,
                email VARCHAR(180) NULL,
                phone VARCHAR(64) NULL,
                notes TEXT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_card (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                supplier_id INTEGER NULL REFERENCES supplier(id) ON DELETE SET NULL,
                type VARCHAR(255) NOT NULL,
                uniqueness TEXT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_customer_card_customer ON customer_card (customer_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_card_order (
                id SERIAL PRIMARY KEY,
                card_id INTEGER NOT NULL REFERENCES customer_card(id) ON DELETE CASCADE,
                product_id INTEGER NULL REFERENCES product(id) ON DELETE SET NULL,
                product_name VARCHAR(255) NOT NULL,
                quantity INTEGER NOT NULL,
                ordered_at DATE NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'ordered',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_customer_card_order_card ON customer_card_order (card_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_card_order');
        $this->addSql('DROP TABLE customer_card');
        $this->addSql('DROP TABLE supplier');
    }
}
