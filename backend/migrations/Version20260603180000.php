<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the opportunity and opportunity_stage_change tables (CRM phase 4 —
 * the per-customer sales deals that run through the phase-3 pipeline).
 * An opportunity belongs to a customer (CASCADE), a fixed type and a
 * current stage (both RESTRICT so a pipeline in use can't be deleted),
 * and an optional contact (SET NULL). Stage moves are logged in
 * opportunity_stage_change.
 */
final class Version20260603180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add opportunity + opportunity_stage_change tables (CRM phase 4).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE opportunity (
                id                  SERIAL PRIMARY KEY,
                customer_id         INTEGER      NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                type_id             INTEGER      NOT NULL REFERENCES opportunity_type(id) ON DELETE RESTRICT,
                stage_id            INTEGER      NOT NULL REFERENCES opportunity_stage(id) ON DELETE RESTRICT,
                contact_id          INTEGER          NULL REFERENCES contact(id) ON DELETE SET NULL,
                title               VARCHAR(255) NOT NULL,
                value               NUMERIC(14, 2)   NULL,
                currency            VARCHAR(3)   NOT NULL DEFAULT 'HUF',
                expected_close_date DATE             NULL,
                closed_at           DATE             NULL,
                notes               TEXT             NULL,
                created_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_opportunity_customer ON opportunity (customer_id)');
        $this->addSql('CREATE INDEX idx_opportunity_type ON opportunity (type_id)');
        $this->addSql('CREATE INDEX idx_opportunity_stage ON opportunity (stage_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE opportunity_stage_change (
                id              SERIAL PRIMARY KEY,
                opportunity_id  INTEGER      NOT NULL REFERENCES opportunity(id) ON DELETE CASCADE,
                changed_by_id   INTEGER          NULL REFERENCES app_user(id) ON DELETE SET NULL,
                from_stage_name VARCHAR(255)     NULL,
                to_stage_name   VARCHAR(255) NOT NULL,
                changed_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_opp_stage_change_opportunity ON opportunity_stage_change (opportunity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE opportunity_stage_change');
        $this->addSql('DROP TABLE opportunity');
    }
}
