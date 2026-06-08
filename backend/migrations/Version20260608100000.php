<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Installed devices tab: devices installed at the customer site
 * (name, description, quantity, install date, location). The name may be
 * prefilled from the product catalogue but is freely overridable; the
 * optional product link survives a catalogue deletion as SET NULL.
 */
final class Version20260608100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer_installed_device table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_installed_device (
                id           SERIAL PRIMARY KEY,
                customer_id  INTEGER      NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                product_id   INTEGER          NULL REFERENCES product(id) ON DELETE SET NULL,
                name         VARCHAR(255) NOT NULL,
                description  TEXT             NULL,
                quantity     INTEGER      NOT NULL DEFAULT 1,
                installed_at DATE             NULL,
                location     TEXT             NULL,
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);

        $this->addSql('CREATE INDEX idx_installed_device_customer ON customer_installed_device (customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_installed_device');
    }
}
