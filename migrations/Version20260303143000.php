<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TOTP 2FA fields to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS two_factor_secret');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS two_factor_enabled');
    }
}
