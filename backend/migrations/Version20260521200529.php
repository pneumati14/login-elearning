<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260521200529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson ADD video_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD pdf_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD youtube_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson DROP video_path');
        $this->addSql('ALTER TABLE lesson DROP pdf_path');
        $this->addSql('ALTER TABLE lesson DROP youtube_url');
    }
}
