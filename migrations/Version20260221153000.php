<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add advanced QR check-in columns for reservations (qr_token, used_at, checked_in_at, qr_generated_at)';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('reservation');

        if (!$table->hasColumn('qr_token')) {
            $table->addColumn('qr_token', 'string', ['length' => 64, 'notnull' => false]);
        }
        if (!$table->hasColumn('checked_in_at')) {
            $table->addColumn('checked_in_at', 'datetime', ['notnull' => false]);
        }
        if (!$table->hasColumn('used_at')) {
            $table->addColumn('used_at', 'datetime', ['notnull' => false]);
        }
        if (!$table->hasColumn('qr_generated_at')) {
            $table->addColumn('qr_generated_at', 'datetime', ['notnull' => false]);
        }
        if (!$table->hasIndex('UNIQ_RESERVATION_QR_TOKEN')) {
            $table->addUniqueIndex(['qr_token'], 'UNIQ_RESERVATION_QR_TOKEN');
        }

        $this->addSql("UPDATE reservation SET statut = 'Confirmee' WHERE statut IS NULL OR statut = ''");
        $this->addSql('UPDATE reservation SET qr_token = LOWER(HEX(RANDOM_BYTES(32))) WHERE qr_token IS NULL OR qr_token = \'\'');
        $this->addSql('UPDATE reservation SET qr_generated_at = COALESCE(qr_generated_at, date_reservation)');
        $this->addSql('ALTER TABLE reservation MODIFY qr_token VARCHAR(64) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('reservation');
        if ($table->hasColumn('checked_in_at')) {
            $table->dropColumn('checked_in_at');
        }
        if ($table->hasColumn('used_at')) {
            $table->dropColumn('used_at');
        }
        if ($table->hasColumn('qr_generated_at')) {
            $table->dropColumn('qr_generated_at');
        }
    }
}
