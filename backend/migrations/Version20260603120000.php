<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the contact table: people at a customer company (CRM phase 2).
 * Deleted together with their customer via ON DELETE CASCADE.
 */
final class Version20260603120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contact table (customer contact persons).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE contact (
                id           SERIAL PRIMARY KEY,
                customer_id  INTEGER      NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                first_name   VARCHAR(255) NOT NULL,
                last_name    VARCHAR(255) NOT NULL,
                job_title    VARCHAR(255) DEFAULT NULL,
                email        VARCHAR(180) DEFAULT NULL,
                phone        VARCHAR(64)  DEFAULT NULL,
                mobile       VARCHAR(64)  DEFAULT NULL,
                is_primary   BOOLEAN      NOT NULL DEFAULT FALSE,
                notes        TEXT         DEFAULT NULL,
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_contact_customer ON contact (customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact');
    }
}
