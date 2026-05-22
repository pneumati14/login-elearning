<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make admin-entered content bilingual: every translatable text column on
 * course, lesson, job_position and publication becomes a `<field>_en`
 * (required) / `<field>_hu` (optional) pair. Existing values are kept in
 * the English column.
 */
final class Version20260522043502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert course/lesson/job_position/publication text fields to bilingual *_en / *_hu columns.';
    }

    public function up(Schema $schema): void
    {
        // ── course ──────────────────────────────────────────────────────
        $this->addSql('ALTER TABLE course ADD title_en TEXT');
        $this->addSql('ALTER TABLE course ADD title_hu TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD description_en TEXT');
        $this->addSql('ALTER TABLE course ADD description_hu TEXT DEFAULT NULL');
        $this->addSql("UPDATE course SET title_en = COALESCE(title, ''), description_en = COALESCE(description, '')");
        $this->addSql('ALTER TABLE course ALTER COLUMN title_en SET NOT NULL');
        $this->addSql('ALTER TABLE course ALTER COLUMN description_en SET NOT NULL');
        $this->addSql('ALTER TABLE course DROP title');
        $this->addSql('ALTER TABLE course DROP description');

        // ── job_position ────────────────────────────────────────────────
        $this->addSql('ALTER TABLE job_position ADD title_en TEXT');
        $this->addSql('ALTER TABLE job_position ADD title_hu TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD location_en TEXT');
        $this->addSql('ALTER TABLE job_position ADD location_hu TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD type_en TEXT');
        $this->addSql('ALTER TABLE job_position ADD type_hu TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_position ADD summary_en TEXT');
        $this->addSql('ALTER TABLE job_position ADD summary_hu TEXT DEFAULT NULL');
        $this->addSql("UPDATE job_position SET title_en = COALESCE(title, ''), location_en = COALESCE(location, ''), type_en = COALESCE(type, ''), summary_en = COALESCE(summary, '')");
        $this->addSql('ALTER TABLE job_position ALTER COLUMN title_en SET NOT NULL');
        $this->addSql('ALTER TABLE job_position ALTER COLUMN location_en SET NOT NULL');
        $this->addSql('ALTER TABLE job_position ALTER COLUMN type_en SET NOT NULL');
        $this->addSql('ALTER TABLE job_position ALTER COLUMN summary_en SET NOT NULL');
        $this->addSql('ALTER TABLE job_position DROP title');
        $this->addSql('ALTER TABLE job_position DROP location');
        $this->addSql('ALTER TABLE job_position DROP type');
        $this->addSql('ALTER TABLE job_position DROP summary');

        // ── lesson ──────────────────────────────────────────────────────
        $this->addSql('ALTER TABLE lesson ADD title_en TEXT');
        $this->addSql('ALTER TABLE lesson ADD title_hu TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD content_en TEXT');
        $this->addSql('ALTER TABLE lesson ADD content_hu TEXT DEFAULT NULL');
        $this->addSql("UPDATE lesson SET title_en = COALESCE(title, ''), content_en = COALESCE(content, '')");
        $this->addSql('ALTER TABLE lesson ALTER COLUMN title_en SET NOT NULL');
        $this->addSql('ALTER TABLE lesson ALTER COLUMN content_en SET NOT NULL');
        $this->addSql('ALTER TABLE lesson DROP title');
        $this->addSql('ALTER TABLE lesson DROP content');

        // ── publication ─────────────────────────────────────────────────
        $this->addSql('ALTER TABLE publication ADD title_en TEXT');
        $this->addSql('ALTER TABLE publication ADD title_hu TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD description_en TEXT');
        $this->addSql('ALTER TABLE publication ADD description_hu TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD topic_en TEXT');
        $this->addSql('ALTER TABLE publication ADD topic_hu TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD author_en TEXT');
        $this->addSql('ALTER TABLE publication ADD author_hu TEXT DEFAULT NULL');
        $this->addSql("UPDATE publication SET title_en = COALESCE(title, ''), description_en = COALESCE(description, ''), topic_en = COALESCE(topic, ''), author_en = COALESCE(author, '')");
        $this->addSql('ALTER TABLE publication ALTER COLUMN title_en SET NOT NULL');
        $this->addSql('ALTER TABLE publication ALTER COLUMN description_en SET NOT NULL');
        $this->addSql('ALTER TABLE publication ALTER COLUMN topic_en SET NOT NULL');
        $this->addSql('ALTER TABLE publication ALTER COLUMN author_en SET NOT NULL');
        $this->addSql('ALTER TABLE publication DROP title');
        $this->addSql('ALTER TABLE publication DROP description');
        $this->addSql('ALTER TABLE publication DROP topic');
        $this->addSql('ALTER TABLE publication DROP author');
    }

    public function down(Schema $schema): void
    {
        // ── course ──────────────────────────────────────────────────────
        $this->addSql('ALTER TABLE course ADD title VARCHAR(255)');
        $this->addSql('ALTER TABLE course ADD description TEXT DEFAULT NULL');
        $this->addSql('UPDATE course SET title = title_en, description = description_en');
        $this->addSql('ALTER TABLE course ALTER COLUMN title SET NOT NULL');
        $this->addSql('ALTER TABLE course DROP title_en');
        $this->addSql('ALTER TABLE course DROP title_hu');
        $this->addSql('ALTER TABLE course DROP description_en');
        $this->addSql('ALTER TABLE course DROP description_hu');

        // ── job_position ────────────────────────────────────────────────
        $this->addSql('ALTER TABLE job_position ADD title VARCHAR(255)');
        $this->addSql('ALTER TABLE job_position ADD location VARCHAR(255)');
        $this->addSql('ALTER TABLE job_position ADD type VARCHAR(255)');
        $this->addSql('ALTER TABLE job_position ADD summary TEXT');
        $this->addSql('UPDATE job_position SET title = title_en, location = location_en, type = type_en, summary = summary_en');
        $this->addSql('ALTER TABLE job_position ALTER COLUMN title SET NOT NULL');
        $this->addSql('ALTER TABLE job_position ALTER COLUMN location SET NOT NULL');
        $this->addSql('ALTER TABLE job_position ALTER COLUMN type SET NOT NULL');
        $this->addSql('ALTER TABLE job_position ALTER COLUMN summary SET NOT NULL');
        $this->addSql('ALTER TABLE job_position DROP title_en');
        $this->addSql('ALTER TABLE job_position DROP title_hu');
        $this->addSql('ALTER TABLE job_position DROP location_en');
        $this->addSql('ALTER TABLE job_position DROP location_hu');
        $this->addSql('ALTER TABLE job_position DROP type_en');
        $this->addSql('ALTER TABLE job_position DROP type_hu');
        $this->addSql('ALTER TABLE job_position DROP summary_en');
        $this->addSql('ALTER TABLE job_position DROP summary_hu');

        // ── lesson ──────────────────────────────────────────────────────
        $this->addSql('ALTER TABLE lesson ADD title VARCHAR(255)');
        $this->addSql('ALTER TABLE lesson ADD content TEXT DEFAULT NULL');
        $this->addSql('UPDATE lesson SET title = title_en, content = content_en');
        $this->addSql('ALTER TABLE lesson ALTER COLUMN title SET NOT NULL');
        $this->addSql('ALTER TABLE lesson DROP title_en');
        $this->addSql('ALTER TABLE lesson DROP title_hu');
        $this->addSql('ALTER TABLE lesson DROP content_en');
        $this->addSql('ALTER TABLE lesson DROP content_hu');

        // ── publication ─────────────────────────────────────────────────
        $this->addSql('ALTER TABLE publication ADD title VARCHAR(255)');
        $this->addSql('ALTER TABLE publication ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD topic VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD author VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE publication SET title = title_en, description = description_en, topic = topic_en, author = author_en');
        $this->addSql('ALTER TABLE publication ALTER COLUMN title SET NOT NULL');
        $this->addSql('ALTER TABLE publication DROP title_en');
        $this->addSql('ALTER TABLE publication DROP title_hu');
        $this->addSql('ALTER TABLE publication DROP description_en');
        $this->addSql('ALTER TABLE publication DROP description_hu');
        $this->addSql('ALTER TABLE publication DROP topic_en');
        $this->addSql('ALTER TABLE publication DROP topic_hu');
        $this->addSql('ALTER TABLE publication DROP author_en');
        $this->addSql('ALTER TABLE publication DROP author_hu');
    }
}
