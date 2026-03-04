<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add secure QR token and confirmation status to reservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE reservation ADD qr_token VARCHAR(64) DEFAULT NULL, ADD statut VARCHAR(30) NOT NULL DEFAULT 'Confirmee'");
        $this->addSql("UPDATE reservation SET statut = 'Confirmee' WHERE statut IS NULL OR statut = ''");
        $this->addSql('UPDATE reservation SET qr_token = LOWER(HEX(RANDOM_BYTES(32))) WHERE qr_token IS NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_RESERVATION_QR_TOKEN ON reservation (qr_token)');
        $this->addSql('CREATE INDEX IDX_RESERVATION_STATUT ON reservation (statut)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_RESERVATION_STATUT ON reservation');
        $this->addSql('DROP INDEX UNIQ_RESERVATION_QR_TOKEN ON reservation');
        $this->addSql('ALTER TABLE reservation DROP qr_token, DROP statut');
    }
}
