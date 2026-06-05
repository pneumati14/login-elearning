<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Whole-fee raises become their own history rows (percent + effective
 * date) instead of rolling the fee items over — the items keep their
 * list prices and the raises stack on the totals.
 */
final class Version20260605160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer fee-raise history table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_fee_raise (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                percent NUMERIC(7, 2) NOT NULL,
                effective_from DATE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_customer_fee_raise_customer ON customer_fee_raise (customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_fee_raise');
    }
}
