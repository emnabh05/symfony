<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create supplement_review table for product ratings and comments';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('supplement_review')) {
            return;
        }
        $this->addSql('CREATE TABLE supplement_review (
            id INT AUTO_INCREMENT NOT NULL,
            supplement_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(180) DEFAULT NULL,
            rating SMALLINT NOT NULL,
            comment LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_SUPPLEMENT_REVIEW_SUPPLEMENT (supplement_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE supplement_review ADD CONSTRAINT FK_SUPPLEMENT_REVIEW_SUPPLEMENT FOREIGN KEY (supplement_id) REFERENCES supplement (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE supplement_review');
    }
}
