<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Activities gain an optional "assignee" (the responsible user, SET NULL)
 * and the customer link becomes optional, so a task can stand on its own
 * (created from the dashboard without a customer).
 */
final class Version20260603250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activity: add optional assignee; make customer optional.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN assignee_id INTEGER NULL REFERENCES app_user(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_activity_assignee ON activity (assignee_id)');
        $this->addSql('ALTER TABLE activity ALTER COLUMN customer_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Standalone (customer-less) tasks must go before the column is
        // required again, otherwise the NOT NULL constraint would fail.
        $this->addSql('DELETE FROM activity WHERE customer_id IS NULL');
        $this->addSql('ALTER TABLE activity ALTER COLUMN customer_id SET NOT NULL');
        $this->addSql('DROP INDEX idx_activity_assignee');
        $this->addSql('ALTER TABLE activity DROP COLUMN assignee_id');
    }
}
