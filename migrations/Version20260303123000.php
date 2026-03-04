<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create qr_login_sessions table and ensure users.face_id_reference_token exists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS face_id_reference_token VARCHAR(255) DEFAULT NULL');

        $this->addSql(
            'CREATE TABLE IF NOT EXISTS qr_login_sessions (
                id INT AUTO_INCREMENT NOT NULL,
                approved_by_user_id INT DEFAULT NULL,
                token_hash VARCHAR(64) NOT NULL,
                initiator_session_hash VARCHAR(64) NOT NULL,
                initiator_user_agent_hash VARCHAR(64) DEFAULT NULL,
                initiator_ip_hash VARCHAR(64) DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                consumed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_qr_token_hash (token_hash),
                INDEX IDX_qr_status_expires (status, expires_at),
                INDEX IDX_qr_created (created_at),
                INDEX IDX_qr_approved_by_user (approved_by_user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );

        $this->addSql('ALTER TABLE qr_login_sessions ADD CONSTRAINT FK_qr_approved_by_user FOREIGN KEY (approved_by_user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qr_login_sessions DROP FOREIGN KEY FK_qr_approved_by_user');
        $this->addSql('DROP TABLE IF EXISTS qr_login_sessions');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS face_id_reference_token');
    }
}
