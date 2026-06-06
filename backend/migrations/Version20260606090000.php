<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Preliminary effort estimate rows on opportunities: named pieces of
 * work, each with one kind of effort (development / PM) entered in days
 * or hours. The unit is stored as entered; reporting converts hours to
 * days (8h workday).
 */
final class Version20260606090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add opportunity_effort_estimate table (preliminary dev/PM effort rows).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE opportunity_effort_estimate (
                id             SERIAL PRIMARY KEY,
                opportunity_id INTEGER       NOT NULL REFERENCES opportunity(id) ON DELETE CASCADE,
                name           VARCHAR(255)  NOT NULL,
                effort_type    VARCHAR(16)   NOT NULL DEFAULT 'development',
                amount         NUMERIC(7, 2) NOT NULL DEFAULT 0,
                unit           VARCHAR(8)    NOT NULL DEFAULT 'day',
                position       INTEGER       NOT NULL DEFAULT 0
            )
        SQL);

        $this->addSql('CREATE INDEX idx_opp_effort_opportunity ON opportunity_effort_estimate (opportunity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE opportunity_effort_estimate');
    }
}
