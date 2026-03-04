<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create events and participation tables for integrated fitopia-events module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE events (id_event INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, date_event DATE NOT NULL, lieu VARCHAR(150) NOT NULL, capacite INT NOT NULL, type_event VARCHAR(100) NOT NULL, image_event VARCHAR(255) DEFAULT NULL, prix_event NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id_event)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE participation (id_participation INT AUTO_INCREMENT NOT NULL, id_event INT NOT NULL, nom_participant VARCHAR(150) NOT NULL, email_participant VARCHAR(150) NOT NULL, date_inscription DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_AB55E24A3FB41356 (id_event), PRIMARY KEY(id_participation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24A3FB41356 FOREIGN KEY (id_event) REFERENCES events (id_event) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24A3FB41356');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE events');
    }
}
