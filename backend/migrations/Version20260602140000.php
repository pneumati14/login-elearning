<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the customer table for the admin CRM area. Soft-delete via
 * deleted_at; validity period via valid_from / valid_until (both
 * optional). Future phases will link contacts / opportunities /
 * activities to customer.id.
 */
final class Version20260602140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer table for the admin CRM (phase 1).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer (
                id              SERIAL PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                address         TEXT DEFAULT NULL,
                website         VARCHAR(255) DEFAULT NULL,
                billing_address TEXT DEFAULT NULL,
                tax_number      VARCHAR(64)  DEFAULT NULL,
                email           VARCHAR(180) DEFAULT NULL,
                phone           VARCHAR(64)  DEFAULT NULL,
                notes           TEXT DEFAULT NULL,
                owner_id        INTEGER DEFAULT NULL,
                valid_from      DATE DEFAULT NULL,
                valid_until     DATE DEFAULT NULL,
                deleted_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_customer_deleted_at ON customer (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer');
    }
}
