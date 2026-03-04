<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216002000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create fitness_trend table and seed initial trend items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fitness_trend (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, category VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, image_url VARCHAR(500) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $rows = [
            ['Course à pied', 'Endurance', '/images/trends/course-a-pied.jpg'],
            ['Natation', 'Endurance', '/images/trends/natation.jpg'],
            ['Pilates', 'Salles & cours fitness', '/images/trends/pilates.jpg'],
            ['Danse fitness', 'Salles & cours fitness', '/images/trends/danse-fitness.jpg'],
            ['Wearables smart', 'Tech & digital', '/images/trends/wearables-smart.jpg'],
            ['Apps', 'Tech & digital', '/images/trends/apps.jpg'],
            ['IA coaching', 'Tech & digital', '/images/trends/ia-coaching.jpg'],
            ['Home training', 'Tech & digital', '/images/trends/home-training.jpg'],
            ['Yoga', 'Bien-être', '/images/trends/yoga.jpg'],
            ['Tai chi', 'Bien-être', '/images/trends/tai-chi.jpg'],
        ];

        foreach ($rows as $row) {
            $this->addSql(
                'INSERT INTO fitness_trend (title, category, description, image_url, is_active, created_at, updated_at) VALUES (?, ?, NULL, ?, 1, ?, ?)',
                [$row[0], $row[1], $row[2], $now, $now]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE fitness_trend');
    }
}
