<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link fitness programs and exercises to users for admin ownership management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fitness_program ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fitness_exercise ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fitness_program ADD CONSTRAINT FK_8FE92485A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fitness_exercise ADD CONSTRAINT FK_EA2A2DEAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8FE92485A76ED395 ON fitness_program (user_id)');
        $this->addSql('CREATE INDEX IDX_EA2A2DEAA76ED395 ON fitness_exercise (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fitness_program DROP FOREIGN KEY FK_8FE92485A76ED395');
        $this->addSql('ALTER TABLE fitness_exercise DROP FOREIGN KEY FK_EA2A2DEAA76ED395');
        $this->addSql('DROP INDEX IDX_8FE92485A76ED395 ON fitness_program');
        $this->addSql('DROP INDEX IDX_EA2A2DEAA76ED395 ON fitness_exercise');
        $this->addSql('ALTER TABLE fitness_program DROP user_id');
        $this->addSql('ALTER TABLE fitness_exercise DROP user_id');
    }
}
