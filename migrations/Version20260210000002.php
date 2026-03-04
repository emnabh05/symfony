<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification table for order status updates';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('notification')) {
            return;
        }
        $this->addSql('CREATE TABLE notification (
            id INT AUTO_INCREMENT NOT NULL,
            order_ref_id INT DEFAULT NULL,
            email VARCHAR(180) NOT NULL,
            order_number VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            message LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            read_at DATETIME DEFAULT NULL,
            INDEX IDX_NOTIFICATION_ORDER_REF (order_ref_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_ORDER_REF FOREIGN KEY (order_ref_id) REFERENCES `order` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification');
    }
}
