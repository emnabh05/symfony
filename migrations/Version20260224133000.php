<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create waitlist_entry table for smart waitlist FIFO event flow';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('waitlist_entry')) {
            return;
        }

        $table = $schema->createTable('waitlist_entry');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('id_event', 'integer');
        $table->addColumn('email', 'string', ['length' => 150]);
        $table->addColumn('nom', 'string', ['length' => 150]);
        $table->addColumn('status', 'string', ['length' => 20]);
        $table->addColumn('position', 'integer', ['default' => 0]);
        $table->addColumn('token', 'string', ['length' => 64, 'notnull' => false]);
        $table->addColumn('invited_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['id_event', 'email'], 'UNIQ_WAITLIST_EVENT_EMAIL');
        $table->addUniqueIndex(['token'], 'UNIQ_WAITLIST_TOKEN');
        $table->addIndex(['id_event', 'status', 'created_at'], 'IDX_WAITLIST_EVENT_STATUS_CREATED');
        $table->addForeignKeyConstraint(
            'events',
            ['id_event'],
            ['id_event'],
            ['onDelete' => 'CASCADE'],
            'FK_WAITLIST_EVENT'
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('waitlist_entry')) {
            $schema->dropTable('waitlist_entry');
        }
    }
}
