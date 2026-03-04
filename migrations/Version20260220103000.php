<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version20260220103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove payment-specific reservation fields and keep only direct booking data';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('reservation')) {
            return;
        }

        $this->addSql("ALTER TABLE reservation ADD nom VARCHAR(150) NOT NULL DEFAULT '', ADD prenom VARCHAR(150) NOT NULL DEFAULT '', ADD email VARCHAR(180) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE reservation r LEFT JOIN users u ON r.user_id = u.id SET r.nom = COALESCE(NULLIF(u.last_name, ''), ''), r.prenom = COALESCE(NULLIF(u.first_name, ''), ''), r.email = COALESCE(NULLIF(u.email, ''), '')");
        $this->addSql('ALTER TABLE reservation MODIFY montant NUMERIC(10, 2) DEFAULT NULL');

        $this->dropForeignKeyForColumnIfExists('reservation', 'user_id');
        $this->dropIndexIfExists('reservation', 'IDX_42C84955A76ED395');
        $this->dropIndexIfExists('reservation', 'IDX_42C849559A9A7125');
        $this->dropIndexIfExists('reservation', 'UNIQ_42C8495586A24A6A');

        $this->dropColumnIfExists('reservation', 'user_id');
        $this->dropColumnIfExists('reservation', 'statut');
        $this->dropColumnIfExists('reservation', 'transaction_id');
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('Rollback not supported for reservation schema cleanup migration.');
    }

    private function dropForeignKeyForColumnIfExists(string $table, string $column): void
    {
        $this->addSql(sprintf(
            "SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1)",
            $table,
            $column
        ));
        $this->addSql(sprintf(
            "SET @drop_fk_sql = IF(@fk_name IS NOT NULL, CONCAT('ALTER TABLE `%s` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1')",
            $table
        ));
        $this->addSql('PREPARE stmt_drop_fk FROM @drop_fk_sql');
        $this->addSql('EXECUTE stmt_drop_fk');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_fk');
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $this->addSql(sprintf(
            "SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '%s' AND INDEX_NAME = '%s')",
            $table,
            $index
        ));
        $this->addSql(sprintf(
            "SET @drop_idx_sql = IF(@idx_exists > 0, 'ALTER TABLE `%s` DROP INDEX `%s`', 'SELECT 1')",
            $table,
            $index
        ));
        $this->addSql('PREPARE stmt_drop_idx FROM @drop_idx_sql');
        $this->addSql('EXECUTE stmt_drop_idx');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_idx');
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        $this->addSql(sprintf(
            "SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s')",
            $table,
            $column
        ));
        $this->addSql(sprintf(
            "SET @drop_col_sql = IF(@col_exists > 0, 'ALTER TABLE `%s` DROP COLUMN `%s`', 'SELECT 1')",
            $table,
            $column
        ));
        $this->addSql('PREPARE stmt_drop_col FROM @drop_col_sql');
        $this->addSql('EXECUTE stmt_drop_col');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_col');
    }
}
