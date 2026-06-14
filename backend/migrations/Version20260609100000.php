<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Split unit pricing: a per-category flag (split_unit_price) and two new
 * product price parts (material_unit_price, fee_unit_price). For split
 * categories the plain unit_price is computed as material + fee. Enables
 * the flag on the existing Hardver category.
 */
final class Version20260609100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add split unit pricing (category flag + product material/fee parts); enable on Hardver.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_category ADD split_unit_price BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE product ADD material_unit_price NUMERIC(14, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD fee_unit_price NUMERIC(14, 2) DEFAULT NULL');

        $this->addSql("UPDATE product_category SET split_unit_price = true WHERE name = 'Hardver'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP fee_unit_price');
        $this->addSql('ALTER TABLE product DROP material_unit_price');
        $this->addSql('ALTER TABLE product_category DROP split_unit_price');
    }
}
