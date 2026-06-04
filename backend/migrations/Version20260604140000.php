<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Customers gain a status (existing / potential) and a recurring monthly
 * fee (amount + currency) shown and sorted in the customer list. All
 * existing rows start as "potential" — the team flags the paying ones.
 */
final class Version20260604140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer: add status (existing/potential) and monthly fee.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE customer ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'potential'");
        $this->addSql('ALTER TABLE customer ADD COLUMN monthly_fee NUMERIC(14, 2) NULL');
        $this->addSql("ALTER TABLE customer ADD COLUMN monthly_fee_currency VARCHAR(3) NOT NULL DEFAULT 'HUF'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP COLUMN monthly_fee_currency');
        $this->addSql('ALTER TABLE customer DROP COLUMN monthly_fee');
        $this->addSql('ALTER TABLE customer DROP COLUMN status');
    }
}
