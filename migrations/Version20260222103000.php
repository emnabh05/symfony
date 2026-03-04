<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create favorites and reviews tables for events front-office engagement';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('favorites')) {
            $favorites = $schema->createTable('favorites');
            $favorites->addColumn('id', 'integer', ['autoincrement' => true]);
            $favorites->addColumn('id_event', 'integer');
            $favorites->addColumn('email_participant', 'string', ['length' => 180]);
            $favorites->addColumn('created_at', 'datetime_immutable');
            $favorites->setPrimaryKey(['id']);
            $favorites->addUniqueIndex(['id_event', 'email_participant'], 'UNIQ_FAVORITES_EVENT_EMAIL');
            $favorites->addIndex(['id_event'], 'IDX_FAVORITES_EVENT');
            $favorites->addIndex(['email_participant'], 'IDX_FAVORITES_EMAIL');
            $favorites->addForeignKeyConstraint(
                'events',
                ['id_event'],
                ['id_event'],
                ['onDelete' => 'CASCADE'],
                'FK_FAVORITES_EVENT'
            );
        }

        if (!$schema->hasTable('reviews')) {
            $reviews = $schema->createTable('reviews');
            $reviews->addColumn('id', 'integer', ['autoincrement' => true]);
            $reviews->addColumn('id_event', 'integer');
            $reviews->addColumn('email_participant', 'string', ['length' => 180]);
            $reviews->addColumn('note', 'smallint');
            $reviews->addColumn('commentaire', 'text');
            $reviews->addColumn('created_at', 'datetime_immutable');
            $reviews->addColumn('updated_at', 'datetime_immutable', ['notnull' => false]);
            $reviews->setPrimaryKey(['id']);
            $reviews->addUniqueIndex(['id_event', 'email_participant'], 'UNIQ_REVIEWS_EVENT_EMAIL');
            $reviews->addIndex(['id_event'], 'IDX_REVIEWS_EVENT');
            $reviews->addForeignKeyConstraint(
                'events',
                ['id_event'],
                ['id_event'],
                ['onDelete' => 'CASCADE'],
                'FK_REVIEWS_EVENT'
            );
            $this->addSql('ALTER TABLE reviews ADD CONSTRAINT CHK_REVIEWS_NOTE_RANGE CHECK (note >= 1 AND note <= 5)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('reviews')) {
            $schema->dropTable('reviews');
        }
        if ($schema->hasTable('favorites')) {
            $schema->dropTable('favorites');
        }
    }
}
