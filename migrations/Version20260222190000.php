<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create monthly_xp_winner table for leaderboard reward winners';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('monthly_xp_winner')) {
            return;
        }

        $this->addSql('CREATE TABLE monthly_xp_winner (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            month_key VARCHAR(7) NOT NULL,
            xp INT NOT NULL,
            total_intakes INT NOT NULL,
            active_days INT NOT NULL,
            longest_streak INT NOT NULL,
            reward_product_name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_monthly_xp_winner_month (month_key),
            INDEX IDX_MONTHLY_XP_WINNER_USER (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE monthly_xp_winner ADD CONSTRAINT FK_MONTHLY_XP_WINNER_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('monthly_xp_winner')) {
            return;
        }

        $this->addSql('ALTER TABLE monthly_xp_winner DROP FOREIGN KEY FK_MONTHLY_XP_WINNER_USER');
        $this->addSql('DROP TABLE monthly_xp_winner');
    }
}

