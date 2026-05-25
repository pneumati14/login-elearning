<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add Spanish (`*_es`) columns alongside the existing `*_en` / `*_hu` /
 * `*_az` / `*_de` / `*_pt` / `*_tr` / `*_pl` columns for every translatable
 * admin-entered text field. All Spanish columns are nullable so the
 * existing content stays valid and falls back to English until an admin
 * fills the new fields in.
 */
final class Version20260525130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Spanish (*_es) columns to course, lesson, job_position and publication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course ADD title_es TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD description_es TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE lesson ADD title_es TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD content_es TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE job_position ADD title_es TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD location_es TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD type_es TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD summary_es TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE publication ADD title_es TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD description_es TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD topic_es TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD author_es TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP title_es');
        $this->addSql('ALTER TABLE course DROP description_es');

        $this->addSql('ALTER TABLE lesson DROP title_es');
        $this->addSql('ALTER TABLE lesson DROP content_es');

        $this->addSql('ALTER TABLE job_position DROP title_es');
        $this->addSql('ALTER TABLE job_position DROP location_es');
        $this->addSql('ALTER TABLE job_position DROP type_es');
        $this->addSql('ALTER TABLE job_position DROP summary_es');

        $this->addSql('ALTER TABLE publication DROP title_es');
        $this->addSql('ALTER TABLE publication DROP description_es');
        $this->addSql('ALTER TABLE publication DROP topic_es');
        $this->addSql('ALTER TABLE publication DROP author_es');
    }
}
