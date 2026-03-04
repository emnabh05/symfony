<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supplement table migration
 */
final class Version20260203000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create supplement table for nutrition supplements management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE supplement (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            brand VARCHAR(100) NOT NULL,
            price NUMERIC(10, 2) NOT NULL,
            stock INT NOT NULL,
            calories INT DEFAULT NULL,
            description LONGTEXT NOT NULL,
            image VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_category (category),
            INDEX idx_brand (brand),
            INDEX idx_stock (stock)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE supplement');
    }
}

