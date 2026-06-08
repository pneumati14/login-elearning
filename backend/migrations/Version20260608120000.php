<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed the Szoftver sub-categories (Integráció, Egyszeri díj, Havi díj).
 * Runs once after the taxonomy tables exist (Version20260608110000).
 */
final class Version20260608120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed Szoftver product sub-categories (Integráció, Egyszeri díj, Havi díj).';
    }

    public function up(Schema $schema): void
    {
        $subs = ['Integráció', 'Egyszeri díj', 'Havi díj'];
        $position = 0;
        foreach ($subs as $sub) {
            $this->addSql(
                "INSERT INTO product_subcategory (category_id, name, position)
                 SELECT id, :name, :pos FROM product_category WHERE name = 'Szoftver'",
                ['name' => $sub, 'pos' => $position++],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM product_subcategory
             WHERE name IN ('Integráció', 'Egyszeri díj', 'Havi díj')
               AND category_id IN (SELECT id FROM product_category WHERE name = 'Szoftver')",
        );
    }
}
