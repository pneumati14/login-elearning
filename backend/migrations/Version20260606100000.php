<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Exchange rate on currency settings: 1 unit of the currency in HUF
 * (HUF itself is fixed at 1). Used by the pipeline report to convert
 * mixed-currency totals into the selected report currency; editable
 * inline on the report's filter bar.
 */
final class Version20260606100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'currency_setting: add rate_huf (1 unit in HUF) for report conversion.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE currency_setting ADD rate_huf NUMERIC(14, 6) NULL');
        $this->addSql("UPDATE currency_setting SET rate_huf = 1 WHERE currency = 'HUF'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE currency_setting DROP COLUMN rate_huf');
    }
}
