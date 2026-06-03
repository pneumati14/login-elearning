<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the product catalogue and opportunity line items (CRM phase 4.5).
 * Products are plain admin-managed config. A line item belongs to an
 * opportunity (CASCADE) and optionally references a product (SET NULL,
 * with the name and unit price snapshotted on the line so history
 * survives catalogue changes).
 */
final class Version20260603200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product + opportunity_line_item tables (CRM phase 4.5).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product (
                id          SERIAL PRIMARY KEY,
                name        VARCHAR(255)  NOT NULL,
                sku         VARCHAR(64)       NULL,
                description TEXT              NULL,
                unit_price  NUMERIC(14, 2)    NULL,
                currency    VARCHAR(3)    NOT NULL DEFAULT 'HUF',
                is_active   BOOLEAN       NOT NULL DEFAULT TRUE,
                valid_from  DATE              NULL,
                valid_until DATE              NULL,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE opportunity_line_item (
                id             SERIAL PRIMARY KEY,
                opportunity_id INTEGER       NOT NULL REFERENCES opportunity(id) ON DELETE CASCADE,
                product_id     INTEGER           NULL REFERENCES product(id) ON DELETE SET NULL,
                product_name   VARCHAR(255)  NOT NULL,
                quantity       NUMERIC(12, 2) NOT NULL DEFAULT 1,
                unit_price     NUMERIC(14, 2) NOT NULL DEFAULT 0,
                position       INTEGER       NOT NULL DEFAULT 0
            )
        SQL);

        $this->addSql('CREATE INDEX idx_opp_line_item_opportunity ON opportunity_line_item (opportunity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE opportunity_line_item');
        $this->addSql('DROP TABLE product');
    }
}
