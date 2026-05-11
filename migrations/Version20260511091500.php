<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511091500 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add place column to fitness_exercise for gym/home filtering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE fitness_exercise ADD place VARCHAR(20) NOT NULL DEFAULT 'both' AFTER duration");
        $this->addSql("UPDATE fitness_exercise SET place = 'both' WHERE place IS NULL OR place = ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fitness_exercise DROP place');
    }
}
