<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create order and order_item tables for shop checkout flow';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('order')) {
            $this->addSql('CREATE TABLE `order` (
                id INT AUTO_INCREMENT NOT NULL,
                order_number VARCHAR(50) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(180) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                address VARCHAR(255) NOT NULL,
                city VARCHAR(100) NOT NULL,
                postal_code VARCHAR(20) NOT NULL,
                payment_method VARCHAR(100) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT \'pending\',
                subtotal NUMERIC(10, 2) NOT NULL,
                shipping NUMERIC(10, 2) NOT NULL,
                discount NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
                total NUMERIC(10, 2) NOT NULL,
                discount_code VARCHAR(50) DEFAULT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_ORDER_NUMBER (order_number),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schema->hasTable('order_item')) {
            $this->addSql('CREATE TABLE order_item (
                id INT AUTO_INCREMENT NOT NULL,
                order_id INT NOT NULL,
                supplement_id INT NOT NULL,
                quantity INT NOT NULL,
                price NUMERIC(10, 2) NOT NULL,
                total NUMERIC(10, 2) NOT NULL,
                INDEX IDX_ORDER_ITEM_ORDER (order_id),
                INDEX IDX_ORDER_ITEM_SUPPLEMENT (supplement_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_ORDER_ITEM_ORDER FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_ORDER_ITEM_SUPPLEMENT FOREIGN KEY (supplement_id) REFERENCES supplement (id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS order_item');
        $this->addSql('DROP TABLE IF EXISTS `order`');
    }
}
