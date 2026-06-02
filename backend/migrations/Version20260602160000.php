<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the customer_sales_assignment table to track who is responsible
 * for which customer over time (multiple concurrent assignments
 * allowed; period via valid_from / valid_until). Drops the unused
 * customer.owner_id column that was a phase-1 placeholder.
 */
final class Version20260602160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer_sales_assignment; drop unused customer.owner_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_sales_assignment (
                id           SERIAL PRIMARY KEY,
                customer_id  INTEGER      NOT NULL REFERENCES customer(id)  ON DELETE CASCADE,
                user_id      INTEGER      NOT NULL REFERENCES app_user(id)  ON DELETE RESTRICT,
                valid_from   DATE         DEFAULT NULL,
                valid_until  DATE         DEFAULT NULL,
                notes        TEXT         DEFAULT NULL,
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_csa_customer ON customer_sales_assignment (customer_id)');
        $this->addSql('CREATE INDEX idx_csa_user     ON customer_sales_assignment (user_id)');

        $this->addSql('ALTER TABLE customer DROP COLUMN owner_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD owner_id INTEGER DEFAULT NULL');
        $this->addSql('DROP TABLE customer_sales_assignment');
    }
}
