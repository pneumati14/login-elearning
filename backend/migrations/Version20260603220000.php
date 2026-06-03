<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CRM phase 4.6: a manual quote number on opportunities and uploadable
 * offer documents (PDFs). A document belongs to one opportunity
 * (CASCADE) and references the uploading admin (SET NULL).
 */
final class Version20260603220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add opportunity.quote_number + opportunity_document table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity ADD quote_number VARCHAR(64) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE opportunity_document (
                id             SERIAL PRIMARY KEY,
                opportunity_id INTEGER      NOT NULL REFERENCES opportunity(id) ON DELETE CASCADE,
                uploaded_by_id INTEGER          NULL REFERENCES app_user(id) ON DELETE SET NULL,
                stored_name    VARCHAR(255) NOT NULL,
                original_name  VARCHAR(255) NOT NULL,
                size           INTEGER          NULL,
                uploaded_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_opp_document_opportunity ON opportunity_document (opportunity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE opportunity_document');
        $this->addSql('ALTER TABLE opportunity DROP quote_number');
    }
}
