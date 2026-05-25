<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add Azerbaijani (`*_az`) columns alongside the existing `*_en` / `*_hu`
 * columns for every translatable admin-entered text field. All Azerbaijani
 * columns are nullable so the existing content stays valid and falls back
 * to English until an admin fills the new fields in.
 */
final class Version20260524120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Azerbaijani (*_az) columns to course, lesson, job_position and publication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course ADD title_az TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD description_az TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE lesson ADD title_az TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD content_az TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE job_position ADD title_az TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD location_az TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD type_az TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD summary_az TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE publication ADD title_az TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD description_az TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD topic_az TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD author_az TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP title_az');
        $this->addSql('ALTER TABLE course DROP description_az');

        $this->addSql('ALTER TABLE lesson DROP title_az');
        $this->addSql('ALTER TABLE lesson DROP content_az');

        $this->addSql('ALTER TABLE job_position DROP title_az');
        $this->addSql('ALTER TABLE job_position DROP location_az');
        $this->addSql('ALTER TABLE job_position DROP type_az');
        $this->addSql('ALTER TABLE job_position DROP summary_az');

        $this->addSql('ALTER TABLE publication DROP title_az');
        $this->addSql('ALTER TABLE publication DROP description_az');
        $this->addSql('ALTER TABLE publication DROP topic_az');
        $this->addSql('ALTER TABLE publication DROP author_az');
    }
}
