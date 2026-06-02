<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace the customer.address / billing_address free-text columns with
 * structured fields: country (ISO 3166-1 alpha-2), city, postal code,
 * street + house number. Same shape for both addresses. Country is the
 * only required-by-shape field — values stay nullable so partial
 * records remain possible.
 */
final class Version20260602150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer: split address / billing_address into country/city/postal_code/street.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP COLUMN address');
        $this->addSql('ALTER TABLE customer DROP COLUMN billing_address');

        $this->addSql('ALTER TABLE customer ADD address_country     VARCHAR(2)   DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD address_city        VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD address_postal_code VARCHAR(16)  DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD address_street      VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE customer ADD billing_address_country     VARCHAR(2)   DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD billing_address_city        VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD billing_address_postal_code VARCHAR(16)  DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD billing_address_street      VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP COLUMN address_country');
        $this->addSql('ALTER TABLE customer DROP COLUMN address_city');
        $this->addSql('ALTER TABLE customer DROP COLUMN address_postal_code');
        $this->addSql('ALTER TABLE customer DROP COLUMN address_street');

        $this->addSql('ALTER TABLE customer DROP COLUMN billing_address_country');
        $this->addSql('ALTER TABLE customer DROP COLUMN billing_address_city');
        $this->addSql('ALTER TABLE customer DROP COLUMN billing_address_postal_code');
        $this->addSql('ALTER TABLE customer DROP COLUMN billing_address_street');

        $this->addSql('ALTER TABLE customer ADD address         TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD billing_address TEXT DEFAULT NULL');
    }
}
