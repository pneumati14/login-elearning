<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Percentage discount on the customer's monthly fee total, so the
 * billing tab shows the really invoiced amount, not the list price.
 */
final class Version20260605130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer monthly-fee discount percent.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD fee_discount_percent NUMERIC(5, 2) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP fee_discount_percent');
    }
}
