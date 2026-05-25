<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add German (`*_de`) columns alongside the existing `*_en` / `*_hu` /
 * `*_az` columns for every translatable admin-entered text field. All
 * German columns are nullable so the existing content stays valid and
 * falls back to English until an admin fills the new fields in.
 */
final class Version20260524130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add German (*_de) columns to course, lesson, job_position and publication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course ADD title_de TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD description_de TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE lesson ADD title_de TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD content_de TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE job_position ADD title_de TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD location_de TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD type_de TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD summary_de TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE publication ADD title_de TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD description_de TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD topic_de TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD author_de TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP title_de');
        $this->addSql('ALTER TABLE course DROP description_de');

        $this->addSql('ALTER TABLE lesson DROP title_de');
        $this->addSql('ALTER TABLE lesson DROP content_de');

        $this->addSql('ALTER TABLE job_position DROP title_de');
        $this->addSql('ALTER TABLE job_position DROP location_de');
        $this->addSql('ALTER TABLE job_position DROP type_de');
        $this->addSql('ALTER TABLE job_position DROP summary_de');

        $this->addSql('ALTER TABLE publication DROP title_de');
        $this->addSql('ALTER TABLE publication DROP description_de');
        $this->addSql('ALTER TABLE publication DROP topic_de');
        $this->addSql('ALTER TABLE publication DROP author_de');
    }
}
