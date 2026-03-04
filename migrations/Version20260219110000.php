<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reservation table for event payment workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, id_event INT NOT NULL, montant NUMERIC(10, 2) NOT NULL, statut VARCHAR(30) NOT NULL, date_reservation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', transaction_id VARCHAR(191) DEFAULT NULL, INDEX IDX_42C84955A76ED395 (user_id), INDEX IDX_42C84955D753C885 (id_event), INDEX IDX_42C849559A9A7125 (statut), INDEX IDX_42C8495547DAA4C4 (date_reservation), UNIQUE INDEX UNIQ_42C8495586A24A6A (transaction_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955D753C885 FOREIGN KEY (id_event) REFERENCES events (id_event) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A76ED395');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955D753C885');
        $this->addSql('DROP TABLE reservation');
    }
}
