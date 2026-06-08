<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Product taxonomy: admin-managed categories (Hardver / Szoftver) with
 * ordered sub-categories, plus the category_id / subcategory_id FKs on
 * the product table (ON DELETE SET NULL so deleting a category keeps the
 * products and their history). Seeds Hardver + Szoftver and the Hardver
 * sub-categories (Terminál, Kártya olvasó, Vezérlő, Forgóvilla,
 * Forgókapu, Kamera, Beléptető szoftver).
 */
final class Version20260608110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product category / subcategory taxonomy + product FKs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product_category (
                id         SERIAL PRIMARY KEY,
                name       VARCHAR(255) NOT NULL,
                position   INTEGER      NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE product_subcategory (
                id          SERIAL PRIMARY KEY,
                category_id INTEGER      NOT NULL REFERENCES product_category(id) ON DELETE CASCADE,
                name        VARCHAR(255) NOT NULL,
                position    INTEGER      NOT NULL DEFAULT 0
            )
            SQL);
        $this->addSql('CREATE INDEX idx_product_subcategory_category ON product_subcategory (category_id)');

        $this->addSql('ALTER TABLE product ADD category_id INTEGER NULL REFERENCES product_category(id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD subcategory_id INTEGER NULL REFERENCES product_subcategory(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_product_category ON product (category_id)');
        $this->addSql('CREATE INDEX idx_product_subcategory ON product (subcategory_id)');

        // ── Seed the initial taxonomy ─────────────────────────────────
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->addSql(
            'INSERT INTO product_category (name, position, created_at, updated_at) VALUES (:name, :pos, :now, :now)',
            ['name' => 'Hardver', 'pos' => 0, 'now' => $now],
        );
        $this->addSql(
            'INSERT INTO product_category (name, position, created_at, updated_at) VALUES (:name, :pos, :now, :now)',
            ['name' => 'Szoftver', 'pos' => 1, 'now' => $now],
        );

        $hardwareSubs = ['Terminál', 'Kártya olvasó', 'Vezérlő', 'Forgóvilla', 'Forgókapu', 'Kamera', 'Beléptető szoftver'];
        $position = 0;
        foreach ($hardwareSubs as $sub) {
            $this->addSql(
                "INSERT INTO product_subcategory (category_id, name, position)
                 SELECT id, :name, :pos FROM product_category WHERE name = 'Hardver'",
                ['name' => $sub, 'pos' => $position++],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP subcategory_id');
        $this->addSql('ALTER TABLE product DROP category_id');
        $this->addSql('DROP TABLE product_subcategory');
        $this->addSql('DROP TABLE product_category');
    }
}
