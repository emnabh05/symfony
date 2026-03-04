<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create fitness_plan table and join it to fitness_program.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('fitness_plan')) {
            $table = $schema->getTable('fitness_plan');
            if (!$table->hasColumn('exercises_data')) {
                $this->addSql('ALTER TABLE fitness_plan ADD exercises_data JSON NOT NULL');
            }
            if (!$table->hasForeignKey('FK_17AD20A13EB8070A')) {
                $this->addSql('ALTER TABLE fitness_plan ADD CONSTRAINT FK_17AD20A13EB8070A FOREIGN KEY (program_id) REFERENCES fitness_program (id) ON DELETE SET NULL');
            }
            if (!$table->hasForeignKey('FK_17AD20A1A76ED395')) {
                $this->addSql('ALTER TABLE fitness_plan ADD CONSTRAINT FK_17AD20A1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
            }

            return;
        }

        $this->addSql('CREATE TABLE fitness_plan (id INT AUTO_INCREMENT NOT NULL, program_id INT DEFAULT NULL, user_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, place VARCHAR(255) DEFAULT NULL, estimated_minutes INT DEFAULT NULL, exercises_data JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_17AD20A13EB8070A (program_id), INDEX IDX_17AD20A1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE fitness_plan ADD CONSTRAINT FK_17AD20A13EB8070A FOREIGN KEY (program_id) REFERENCES fitness_program (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fitness_plan ADD CONSTRAINT FK_17AD20A1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fitness_plan DROP FOREIGN KEY FK_17AD20A13EB8070A');
        $this->addSql('ALTER TABLE fitness_plan DROP FOREIGN KEY FK_17AD20A1A76ED395');
        $this->addSql('DROP TABLE fitness_plan');
    }
}
