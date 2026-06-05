<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The customer's billing tab: contract number, first invoice date,
 * billing period and fee title on the customer row; an admin-managed
 * fee-title master list; and contract attachments (PDF/Word/image).
 */
final class Version20260605120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer billing tab: billing fields, fee titles, contract files.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE fee_title (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);

        $this->addSql('ALTER TABLE customer ADD contract_number VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE customer ADD first_invoice_date DATE NULL');
        $this->addSql('ALTER TABLE customer ADD billing_period VARCHAR(16) NULL');
        $this->addSql('ALTER TABLE customer ADD fee_title_id INTEGER NULL REFERENCES fee_title(id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE customer_contract_file (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                stored_name VARCHAR(64) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_customer_contract_file_customer ON customer_contract_file (customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_contract_file');
        $this->addSql('ALTER TABLE customer DROP contract_number');
        $this->addSql('ALTER TABLE customer DROP first_invoice_date');
        $this->addSql('ALTER TABLE customer DROP billing_period');
        $this->addSql('ALTER TABLE customer DROP fee_title_id');
        $this->addSql('DROP TABLE fee_title');
    }
}
