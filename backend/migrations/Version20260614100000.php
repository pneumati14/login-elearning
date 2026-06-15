<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-line billing state for opportunity line items: an `invoiced` flag and
 * the timestamp it was set. Lets the billing menu mark individual offer
 * lines as invoiced and derive the offer's invoicing percentage.
 */
final class Version20260614100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-line invoiced state to opportunity line items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity_line_item ADD invoiced BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE opportunity_line_item ADD invoiced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity_line_item DROP invoiced_at');
        $this->addSql('ALTER TABLE opportunity_line_item DROP invoiced');
    }
}
