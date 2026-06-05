<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The card-order workflow's final step is "received" (the customer took
 * delivery), not "paid" — payment is tracked by the proforma steps.
 */
final class Version20260605100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Card orders: rename the final 'paid' status to 'received'.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE customer_card_order SET status = 'received' WHERE status = 'paid'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE customer_card_order SET status = 'paid' WHERE status = 'received'");
    }
}
