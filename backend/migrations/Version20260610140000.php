<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Split unit pricing for opportunity line items: two nullable parts
 * (material_unit_price, fee_unit_price) mirroring the product split. For
 * hardware lines the unit_price is stored as material + fee; plain lines
 * leave them null.
 */
final class Version20260610140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add material/fee unit-price parts to opportunity line items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity_line_item ADD material_unit_price NUMERIC(14, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE opportunity_line_item ADD fee_unit_price NUMERIC(14, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity_line_item DROP fee_unit_price');
        $this->addSql('ALTER TABLE opportunity_line_item DROP material_unit_price');
    }
}
