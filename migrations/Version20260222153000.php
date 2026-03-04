<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link orders to authenticated users for account-based supplement tracking';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('order') && !$schema->getTable('order')->hasColumn('user_id')) {
            $this->addSql('ALTER TABLE `order` ADD user_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_F5299398A76ED395 ON `order` (user_id)');
            $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');

            // Backfill legacy rows when checkout email already matches a registered account email.
            // Explicit collation avoids errors when tables were created with mixed collations.
            $this->addSql('UPDATE `order` o INNER JOIN users u
                ON LOWER(CONVERT(u.email USING utf8mb4)) COLLATE utf8mb4_unicode_ci = LOWER(o.email) COLLATE utf8mb4_unicode_ci
                SET o.user_id = u.id
                WHERE o.user_id IS NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('order') && $schema->getTable('order')->hasColumn('user_id')) {
            $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
            $this->addSql('DROP INDEX IDX_F5299398A76ED395 ON `order`');
            $this->addSql('ALTER TABLE `order` DROP user_id');
        }
    }
}
