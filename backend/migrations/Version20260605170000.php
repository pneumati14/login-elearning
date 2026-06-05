<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Billing tab: billing mode (in advance / in arrears) next to the
 * billing period, plus the payment deadline in days.
 */
final class Version20260605170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer billing mode + payment due days.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD billing_mode VARCHAR(16) NULL');
        $this->addSql('ALTER TABLE customer ADD payment_due_days INTEGER NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP COLUMN billing_mode');
        $this->addSql('ALTER TABLE customer DROP COLUMN payment_due_days');
    }
}
