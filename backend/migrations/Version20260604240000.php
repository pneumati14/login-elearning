<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Card orders get the full workflow: quote → ordered → proforma →
 * proforma paid → shipping → paid (kanban on the cards tab). Existing
 * "delivered" rows map to "shipping"; new orders start as "quote".
 */
final class Version20260604240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CustomerCardOrder: six-step workflow statuses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE customer_card_order SET status = 'shipping' WHERE status = 'delivered'");
        $this->addSql("ALTER TABLE customer_card_order ALTER COLUMN status SET DEFAULT 'quote'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE customer_card_order SET status = 'delivered' WHERE status IN ('shipping', 'paid')");
        $this->addSql("UPDATE customer_card_order SET status = 'ordered' WHERE status NOT IN ('ordered', 'delivered')");
        $this->addSql("ALTER TABLE customer_card_order ALTER COLUMN status SET DEFAULT 'ordered'");
    }
}
