<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CRM phase 5: the activity log / timeline. An activity belongs to a
 * customer (CASCADE) and may reference a contact and/or opportunity
 * (SET NULL) plus the admin who logged it (SET NULL). Tasks use
 * completed_at as their done marker.
 */
final class Version20260603240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the activity table (CRM phase 5 — timeline).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE activity (
                id             SERIAL PRIMARY KEY,
                customer_id    INTEGER      NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                contact_id     INTEGER          NULL REFERENCES contact(id) ON DELETE SET NULL,
                opportunity_id INTEGER          NULL REFERENCES opportunity(id) ON DELETE SET NULL,
                created_by_id  INTEGER          NULL REFERENCES app_user(id) ON DELETE SET NULL,
                type           VARCHAR(16)  NOT NULL DEFAULT 'note',
                subject        VARCHAR(255) NOT NULL,
                body           TEXT             NULL,
                occurred_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                completed_at   TIMESTAMP(0) WITHOUT TIME ZONE NULL,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_activity_customer ON activity (customer_id)');
        $this->addSql('CREATE INDEX idx_activity_opportunity ON activity (opportunity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity');
    }
}
