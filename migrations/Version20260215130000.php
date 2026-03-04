<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create image link table for fitness programs and import images from fitopia_supplements.program';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fitness_program_image_link (id INT AUTO_INCREMENT NOT NULL, program_name VARCHAR(255) NOT NULL, image_url VARCHAR(500) DEFAULT NULL, UNIQUE INDEX UNIQ_FPIL_PROGRAM_NAME (program_name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('INSERT INTO fitness_program_image_link (program_name, image_url) SELECT p.titre, MAX(p.url_image) FROM fitopia_supplements.program p LEFT JOIN fitness_exercise ex ON ex.name = p.titre WHERE p.titre IS NOT NULL GROUP BY p.titre');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE fitness_program_image_link');
    }
}
