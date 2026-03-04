<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add supplement discipline tracking tables and recommended duration';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('supplement') && !$schema->getTable('supplement')->hasColumn('recommended_duration_days')) {
            $this->addSql('ALTER TABLE supplement ADD recommended_duration_days INT NOT NULL DEFAULT 56');
        }

        if (!$schema->hasTable('supplement_intake_log')) {
            $this->addSql('CREATE TABLE supplement_intake_log (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                supplement_id INT NOT NULL,
                intake_date DATE NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_INTAKE_USER_DATE (user_id, intake_date),
                INDEX IDX_INTAKE_USER (user_id),
                INDEX IDX_INTAKE_SUPPLEMENT (supplement_id),
                UNIQUE INDEX uniq_intake_user_supplement_date (user_id, supplement_id, intake_date),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

            $this->addSql('ALTER TABLE supplement_intake_log ADD CONSTRAINT FK_INTAKE_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE supplement_intake_log ADD CONSTRAINT FK_INTAKE_SUPPLEMENT FOREIGN KEY (supplement_id) REFERENCES supplement (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('supplement_intake_log')) {
            $this->addSql('ALTER TABLE supplement_intake_log DROP FOREIGN KEY FK_INTAKE_USER');
            $this->addSql('ALTER TABLE supplement_intake_log DROP FOREIGN KEY FK_INTAKE_SUPPLEMENT');
            $this->addSql('DROP TABLE supplement_intake_log');
        }

        if ($schema->hasTable('supplement') && $schema->getTable('supplement')->hasColumn('recommended_duration_days')) {
            $this->addSql('ALTER TABLE supplement DROP recommended_duration_days');
        }
    }
}
