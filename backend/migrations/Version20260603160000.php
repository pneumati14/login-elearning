<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add an optional validity window (valid_from / valid_until) to
 * opportunity_type. Combined with is_active it drives the displayed
 * status (active / inactive / scheduled / expired).
 */
final class Version20260603160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add valid_from / valid_until to opportunity_type.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity_type ADD valid_from DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE opportunity_type ADD valid_until DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity_type DROP valid_from');
        $this->addSql('ALTER TABLE opportunity_type DROP valid_until');
    }
}
