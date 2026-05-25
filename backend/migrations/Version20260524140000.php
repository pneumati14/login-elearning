<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add Portuguese (`*_pt`) columns alongside the existing `*_en` / `*_hu` /
 * `*_az` / `*_de` columns for every translatable admin-entered text field.
 * All Portuguese columns are nullable so the existing content stays valid
 * and falls back to English until an admin fills the new fields in.
 */
final class Version20260524140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Portuguese (*_pt) columns to course, lesson, job_position and publication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course ADD title_pt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD description_pt TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE lesson ADD title_pt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD content_pt TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE job_position ADD title_pt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD location_pt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD type_pt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD summary_pt TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE publication ADD title_pt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD description_pt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD topic_pt TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD author_pt TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP title_pt');
        $this->addSql('ALTER TABLE course DROP description_pt');

        $this->addSql('ALTER TABLE lesson DROP title_pt');
        $this->addSql('ALTER TABLE lesson DROP content_pt');

        $this->addSql('ALTER TABLE job_position DROP title_pt');
        $this->addSql('ALTER TABLE job_position DROP location_pt');
        $this->addSql('ALTER TABLE job_position DROP type_pt');
        $this->addSql('ALTER TABLE job_position DROP summary_pt');

        $this->addSql('ALTER TABLE publication DROP title_pt');
        $this->addSql('ALTER TABLE publication DROP description_pt');
        $this->addSql('ALTER TABLE publication DROP topic_pt');
        $this->addSql('ALTER TABLE publication DROP author_pt');
    }
}
