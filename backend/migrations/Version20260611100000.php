<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CRM notes page: a private notebook per user. note_folder is the
 * left-hand tree (self-nesting, owner-scoped); note holds the notes
 * (optional folder, owner-scoped); note_submission records each time a
 * note was sent to a customer as an activity (type=note), snapshotting
 * the customer name so the audit line survives later deletions.
 */
final class Version20260611100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CRM notes: note_folder, note, note_submission.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE note_folder (
                id         SERIAL PRIMARY KEY,
                owner_id   INTEGER      NOT NULL REFERENCES app_user(id) ON DELETE CASCADE,
                parent_id  INTEGER      NULL REFERENCES note_folder(id) ON DELETE CASCADE,
                name       VARCHAR(255) NOT NULL,
                position   INTEGER      NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_note_folder_owner ON note_folder (owner_id)');
        $this->addSql('CREATE INDEX idx_note_folder_parent ON note_folder (parent_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE note (
                id         SERIAL PRIMARY KEY,
                owner_id   INTEGER      NOT NULL REFERENCES app_user(id) ON DELETE CASCADE,
                folder_id  INTEGER      NULL REFERENCES note_folder(id) ON DELETE SET NULL,
                title      VARCHAR(255) NOT NULL,
                body       TEXT         NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_note_owner ON note (owner_id)');
        $this->addSql('CREATE INDEX idx_note_folder ON note (folder_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE note_submission (
                id            SERIAL PRIMARY KEY,
                note_id       INTEGER      NOT NULL REFERENCES note(id) ON DELETE CASCADE,
                customer_id   INTEGER      NULL REFERENCES customer(id) ON DELETE SET NULL,
                customer_name VARCHAR(255) NOT NULL,
                activity_id   INTEGER      NULL REFERENCES activity(id) ON DELETE SET NULL,
                sent_by_id    INTEGER      NULL REFERENCES app_user(id) ON DELETE SET NULL,
                sent_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX idx_note_submission_note ON note_submission (note_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE note_submission');
        $this->addSql('DROP TABLE note');
        $this->addSql('DROP TABLE note_folder');
    }
}
