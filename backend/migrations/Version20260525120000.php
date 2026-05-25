<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add Turkish (`*_tr`) and Polish (`*_pl`) columns alongside the existing
 * `*_en` / `*_hu` / `*_az` / `*_de` / `*_pt` columns for every translatable
 * admin-entered text field. All new columns are nullable so the existing
 * content stays valid and falls back to English until an admin fills the
 * new fields in.
 */
final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Turkish (*_tr) and Polish (*_pl) columns to course, lesson, job_position and publication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course ADD title_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD description_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD title_pl TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD description_pl TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE lesson ADD title_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD content_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD title_pl TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD content_pl TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE job_position ADD title_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD location_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD type_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD summary_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD title_pl TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD location_pl TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD type_pl TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD summary_pl TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE publication ADD title_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD description_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD topic_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD author_tr TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD title_pl TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD description_pl TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD topic_pl TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD author_pl TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP title_tr');
        $this->addSql('ALTER TABLE course DROP description_tr');
        $this->addSql('ALTER TABLE course DROP title_pl');
        $this->addSql('ALTER TABLE course DROP description_pl');

        $this->addSql('ALTER TABLE lesson DROP title_tr');
        $this->addSql('ALTER TABLE lesson DROP content_tr');
        $this->addSql('ALTER TABLE lesson DROP title_pl');
        $this->addSql('ALTER TABLE lesson DROP content_pl');

        $this->addSql('ALTER TABLE job_position DROP title_tr');
        $this->addSql('ALTER TABLE job_position DROP location_tr');
        $this->addSql('ALTER TABLE job_position DROP type_tr');
        $this->addSql('ALTER TABLE job_position DROP summary_tr');
        $this->addSql('ALTER TABLE job_position DROP title_pl');
        $this->addSql('ALTER TABLE job_position DROP location_pl');
        $this->addSql('ALTER TABLE job_position DROP type_pl');
        $this->addSql('ALTER TABLE job_position DROP summary_pl');

        $this->addSql('ALTER TABLE publication DROP title_tr');
        $this->addSql('ALTER TABLE publication DROP description_tr');
        $this->addSql('ALTER TABLE publication DROP topic_tr');
        $this->addSql('ALTER TABLE publication DROP author_tr');
        $this->addSql('ALTER TABLE publication DROP title_pl');
        $this->addSql('ALTER TABLE publication DROP description_pl');
        $this->addSql('ALTER TABLE publication DROP topic_pl');
        $this->addSql('ALTER TABLE publication DROP author_pl');
    }
}
