<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create fitness planner tables: programs, exercises and relation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fitness_program (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(100) NOT NULL, level VARCHAR(50) NOT NULL, duration_weeks INT NOT NULL, sessions_per_week INT NOT NULL, session_duration INT NOT NULL, image_url VARCHAR(500) DEFAULT NULL, video_url VARCHAR(500) DEFAULT NULL, is_public TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fitness_exercise (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, muscle_group VARCHAR(100) NOT NULL, difficulty VARCHAR(50) NOT NULL, sets INT DEFAULT NULL, repetitions INT DEFAULT NULL, video_url VARCHAR(500) DEFAULT NULL, image_url VARCHAR(500) DEFAULT NULL, duration INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fitness_program_exercise (fitness_program_id INT NOT NULL, fitness_exercise_id INT NOT NULL, INDEX IDX_C86E7B4586A3EC95 (fitness_program_id), INDEX IDX_C86E7B45B2A9046A (fitness_exercise_id), PRIMARY KEY(fitness_program_id, fitness_exercise_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE fitness_program_exercise ADD CONSTRAINT FK_C86E7B4586A3EC95 FOREIGN KEY (fitness_program_id) REFERENCES fitness_program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fitness_program_exercise ADD CONSTRAINT FK_C86E7B45B2A9046A FOREIGN KEY (fitness_exercise_id) REFERENCES fitness_exercise (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fitness_program_exercise DROP FOREIGN KEY FK_C86E7B4586A3EC95');
        $this->addSql('ALTER TABLE fitness_program_exercise DROP FOREIGN KEY FK_C86E7B45B2A9046A');
        $this->addSql('DROP TABLE fitness_program_exercise');
        $this->addSql('DROP TABLE fitness_program');
        $this->addSql('DROP TABLE fitness_exercise');
    }
}
