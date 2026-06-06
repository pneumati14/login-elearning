<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Customer architecture tab: the integration master list (payroll /
 * ERP / access control / other categories), the per-customer
 * architecture sheet (deployment model, SaaS server, VPN/user notes),
 * its integration links and the typed attachments (diagram / plan /
 * SDD / other).
 */
final class Version20260606120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add integration catalogue + customer architecture tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration (
                id         SERIAL PRIMARY KEY,
                name       VARCHAR(255) NOT NULL,
                category   VARCHAR(32)  NOT NULL DEFAULT 'other',
                is_active  BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE customer_architecture (
                id               SERIAL PRIMARY KEY,
                customer_id      INTEGER      NOT NULL UNIQUE REFERENCES customer(id) ON DELETE CASCADE,
                deployment_model VARCHAR(16)      NULL,
                saas_server      VARCHAR(255)     NULL,
                vpn_info         TEXT             NULL,
                users_info       TEXT             NULL,
                notes            TEXT             NULL,
                updated_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE customer_architecture_integration (
                customer_architecture_id INTEGER NOT NULL REFERENCES customer_architecture(id) ON DELETE CASCADE,
                integration_id           INTEGER NOT NULL REFERENCES integration(id) ON DELETE CASCADE,
                PRIMARY KEY (customer_architecture_id, integration_id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE customer_architecture_file (
                id            SERIAL PRIMARY KEY,
                customer_id   INTEGER      NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                kind          VARCHAR(16)  NOT NULL DEFAULT 'other',
                stored_name   VARCHAR(64)  NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type     VARCHAR(100) NOT NULL,
                created_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);

        $this->addSql('CREATE INDEX idx_customer_architecture_file_customer ON customer_architecture_file (customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_architecture_file');
        $this->addSql('DROP TABLE customer_architecture_integration');
        $this->addSql('DROP TABLE customer_architecture');
        $this->addSql('DROP TABLE integration');
    }
}
