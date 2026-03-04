<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Face ID and password policy columns on users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD face_id_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD face_id_token_hash VARCHAR(255) DEFAULT NULL, ADD face_id_reference_token VARCHAR(255) DEFAULT NULL, ADD face_id_updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD password_policy_level VARCHAR(20) NOT NULL DEFAULT \'standard\', ADD password_must_change TINYINT(1) NOT NULL DEFAULT 0, ADD password_last_changed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP face_id_enabled, DROP face_id_token_hash, DROP face_id_reference_token, DROP face_id_updated_at, DROP password_policy_level, DROP password_must_change, DROP password_last_changed_at');
    }
}
