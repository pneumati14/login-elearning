<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-currency display rounding: how many decimal places the UI rounds
 * each currency to. Seeded with the conventional defaults.
 */
final class Version20260605140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Currency display-rounding settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE currency_setting (
                currency VARCHAR(3) PRIMARY KEY,
                decimals SMALLINT NOT NULL
            )
            SQL);
        $this->addSql("INSERT INTO currency_setting (currency, decimals) VALUES ('HUF', 0), ('EUR', 2), ('USD', 2)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE currency_setting');
    }
}
