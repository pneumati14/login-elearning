<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add an "Egyéb tartozékok" sub-category under the Hardver product
 * category, appended after the existing Hardver sub-categories.
 */
final class Version20260610110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add "Egyéb tartozékok" sub-category under Hardver.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO product_subcategory (category_id, name, position)
            SELECT c.id,
                   'Egyéb tartozékok',
                   COALESCE((SELECT MAX(s.position) + 1
                               FROM product_subcategory s
                              WHERE s.category_id = c.id), 0)
              FROM product_category c
             WHERE c.name = 'Hardver'
               AND NOT EXISTS (
                   SELECT 1 FROM product_subcategory s
                    WHERE s.category_id = c.id
                      AND s.name = 'Egyéb tartozékok'
               )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM product_subcategory
             WHERE name = 'Egyéb tartozékok'
               AND category_id IN (SELECT id FROM product_category WHERE name = 'Hardver')
            SQL);
    }
}
