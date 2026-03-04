<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_premium flag to events for VIP-only premium reservations';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('events')) {
            return;
        }

        $table = $schema->getTable('events');
        if (!$table->hasColumn('is_premium')) {
            $table->addColumn('is_premium', 'boolean', ['default' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('events')) {
            return;
        }

        $table = $schema->getTable('events');
        if ($table->hasColumn('is_premium')) {
            $table->dropColumn('is_premium');
        }
    }
}
