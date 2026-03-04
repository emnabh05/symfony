<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create supplement_review table for product ratings/comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS supplement_review (id INT AUTO_INCREMENT NOT NULL, supplement_id INT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) DEFAULT NULL, rating SMALLINT NOT NULL, comment LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_A4D1EA4FB6B7EA44 (supplement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE supplement_review ADD CONSTRAINT FK_A4D1EA4FB6B7EA44 FOREIGN KEY (supplement_id) REFERENCES supplement (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS supplement_review');
    }
}
