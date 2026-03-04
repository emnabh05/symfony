<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219141422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, order_number VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, order_ref_id INT DEFAULT NULL, INDEX IDX_BF5476CAE238517C (order_ref_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(10, 2) NOT NULL, statut VARCHAR(30) NOT NULL, date_reservation DATETIME NOT NULL, transaction_id VARCHAR(191) DEFAULT NULL, user_id INT NOT NULL, id_event INT NOT NULL, INDEX IDX_42C84955A76ED395 (user_id), INDEX IDX_42C84955D52B4B97 (id_event), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE238517C FOREIGN KEY (order_ref_id) REFERENCES `order` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955D52B4B97 FOREIGN KEY (id_event) REFERENCES events (id_event) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post DROP FOREIGN KEY `FK_BLOG_POST_AUTHOR`');
        $this->addSql('ALTER TABLE blog_post DROP is_pinned, DROP is_locked_comments, DROP is_answered, DROP is_highlighted, DROP moderation_status, CHANGE status status ENUM(\'draft\',\'published\',\'archived\')');
        $this->addSql('DROP INDEX uniq_blog_post_slug ON blog_post');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BA5AE01D989D9B62 ON blog_post (slug)');
        $this->addSql('DROP INDEX idx_blog_post_author ON blog_post');
        $this->addSql('CREATE INDEX IDX_BA5AE01DF675F31B ON blog_post (author_id)');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT `FK_BLOG_POST_AUTHOR` FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX IDX_INTERACTION_TARGET ON content_interaction');
        $this->addSql('ALTER TABLE content_interaction DROP FOREIGN KEY `FK_INTERACTION_USER`');
        $this->addSql('ALTER TABLE content_interaction CHANGE comment_text comment_text LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_interaction_user ON content_interaction');
        $this->addSql('CREATE INDEX IDX_64A75721A76ED395 ON content_interaction (user_id)');
        $this->addSql('ALTER TABLE content_interaction ADD CONSTRAINT `FK_INTERACTION_USER` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_message DROP FOREIGN KEY `FK_DM_MESSAGE_CONV`');
        $this->addSql('ALTER TABLE dm_message DROP FOREIGN KEY `FK_DM_MESSAGE_SENDER`');
        $this->addSql('ALTER TABLE dm_message CHANGE status status VARCHAR(20) NOT NULL, CHANGE attachment_mime attachment_mime VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_dm_message_conv ON dm_message');
        $this->addSql('CREATE INDEX IDX_317DA4E49AC0396 ON dm_message (conversation_id)');
        $this->addSql('DROP INDEX idx_dm_message_sender ON dm_message');
        $this->addSql('CREATE INDEX IDX_317DA4E4F624B39D ON dm_message (sender_id)');
        $this->addSql('ALTER TABLE dm_message ADD CONSTRAINT `FK_DM_MESSAGE_CONV` FOREIGN KEY (conversation_id) REFERENCES dm_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_message ADD CONSTRAINT `FK_DM_MESSAGE_SENDER` FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_participant DROP FOREIGN KEY `FK_DM_PARTICIPANT_CONV`');
        $this->addSql('ALTER TABLE dm_participant DROP FOREIGN KEY `FK_DM_PARTICIPANT_USER`');
        $this->addSql('DROP INDEX idx_dm_participant_conv ON dm_participant');
        $this->addSql('CREATE INDEX IDX_FFE2DC559AC0396 ON dm_participant (conversation_id)');
        $this->addSql('DROP INDEX idx_dm_participant_user ON dm_participant');
        $this->addSql('CREATE INDEX IDX_FFE2DC55A76ED395 ON dm_participant (user_id)');
        $this->addSql('ALTER TABLE dm_participant ADD CONSTRAINT `FK_DM_PARTICIPANT_CONV` FOREIGN KEY (conversation_id) REFERENCES dm_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_participant ADD CONSTRAINT `FK_DM_PARTICIPANT_USER` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE events CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE fitness_exercise DROP FOREIGN KEY `FK_EA2A2DEAA76ED395`');
        $this->addSql('ALTER TABLE fitness_exercise CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_ea2a2deaa76ed395 ON fitness_exercise');
        $this->addSql('CREATE INDEX IDX_19AD82A9A76ED395 ON fitness_exercise (user_id)');
        $this->addSql('ALTER TABLE fitness_exercise ADD CONSTRAINT `FK_EA2A2DEAA76ED395` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fitness_program DROP FOREIGN KEY `FK_8FE92485A76ED395`');
        $this->addSql('ALTER TABLE fitness_program CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_8fe92485a76ed395 ON fitness_program');
        $this->addSql('CREATE INDEX IDX_58E6F9FAA76ED395 ON fitness_program (user_id)');
        $this->addSql('ALTER TABLE fitness_program ADD CONSTRAINT `FK_8FE92485A76ED395` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fitness_program_exercise DROP FOREIGN KEY `FK_C86E7B4586A3EC95`');
        $this->addSql('ALTER TABLE fitness_program_exercise DROP FOREIGN KEY `FK_C86E7B45B2A9046A`');
        $this->addSql('DROP INDEX idx_c86e7b4586a3ec95 ON fitness_program_exercise');
        $this->addSql('CREATE INDEX IDX_E34BCFAE52052ED7 ON fitness_program_exercise (fitness_program_id)');
        $this->addSql('DROP INDEX idx_c86e7b45b2a9046a ON fitness_program_exercise');
        $this->addSql('CREATE INDEX IDX_E34BCFAE113A865A ON fitness_program_exercise (fitness_exercise_id)');
        $this->addSql('ALTER TABLE fitness_program_exercise ADD CONSTRAINT `FK_C86E7B4586A3EC95` FOREIGN KEY (fitness_program_id) REFERENCES fitness_program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fitness_program_exercise ADD CONSTRAINT `FK_C86E7B45B2A9046A` FOREIGN KEY (fitness_exercise_id) REFERENCES fitness_exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_thread DROP FOREIGN KEY `FK_FORUM_THREAD_AUTHOR`');
        $this->addSql('ALTER TABLE forum_thread CHANGE status status ENUM(\'open\',\'closed\',\'pinned\')');
        $this->addSql('DROP INDEX idx_forum_thread_author ON forum_thread');
        $this->addSql('CREATE INDEX IDX_298F7F52A76ED395 ON forum_thread (user_id)');
        $this->addSql('ALTER TABLE forum_thread ADD CONSTRAINT `FK_FORUM_THREAD_AUTHOR` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_f52993988d9f6d38 ON `order`');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F5299398551F0F81 ON `order` (order_number)');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY `FK_52EA1F099D8F7F82`');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY `FK_52EA1F099D8F7F82`');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY `FK_52EA1F09B6B7EA44`');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('DROP INDEX idx_52ea1f099d8f7f82 ON order_item');
        $this->addSql('CREATE INDEX IDX_52EA1F098D9F6D38 ON order_item (order_id)');
        $this->addSql('DROP INDEX idx_52ea1f09b6b7ea44 ON order_item');
        $this->addSql('CREATE INDEX IDX_52EA1F097793FA21 ON order_item (supplement_id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT `FK_52EA1F099D8F7F82` FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT `FK_52EA1F09B6B7EA44` FOREIGN KEY (supplement_id) REFERENCES supplement (id)');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `FK_AB55E24A3FB41356`');
        $this->addSql('ALTER TABLE participation CHANGE date_inscription date_inscription DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_ab55e24a3fb41356 ON participation');
        $this->addSql('CREATE INDEX IDX_AB55E24FD52B4B97 ON participation (id_event)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `FK_AB55E24A3FB41356` FOREIGN KEY (id_event) REFERENCES events (id_event) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE regime_alimentaire DROP FOREIGN KEY `FK_REGIME_USER`');
        $this->addSql('DROP INDEX idx_regime_user ON regime_alimentaire');
        $this->addSql('CREATE INDEX IDX_58CC75A6A76ED395 ON regime_alimentaire (user_id)');
        $this->addSql('ALTER TABLE regime_alimentaire ADD CONSTRAINT `FK_REGIME_USER` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY `FK_REPAS_REGIME`');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY `FK_REPAS_USER`');
        $this->addSql('DROP INDEX idx_repas_user ON repas');
        $this->addSql('CREATE INDEX IDX_A8D351B3A76ED395 ON repas (user_id)');
        $this->addSql('DROP INDEX idx_repas_regime ON repas');
        $this->addSql('CREATE INDEX IDX_A8D351B335E7D534 ON repas (regime_id)');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT `FK_REPAS_REGIME` FOREIGN KEY (regime_id) REFERENCES regime_alimentaire (id_regime) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT `FK_REPAS_USER` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplement_review DROP FOREIGN KEY `FK_A4D1EA4FB6B7EA44`');
        $this->addSql('DROP INDEX idx_a4d1ea4fb6b7ea44 ON supplement_review');
        $this->addSql('CREATE INDEX IDX_9E5C737D7793FA21 ON supplement_review (supplement_id)');
        $this->addSql('ALTER TABLE supplement_review ADD CONSTRAINT `FK_A4D1EA4FB6B7EA44` FOREIGN KEY (supplement_id) REFERENCES supplement (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE238517C');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A76ED395');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955D52B4B97');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('ALTER TABLE blog_post DROP FOREIGN KEY FK_BA5AE01DF675F31B');
        $this->addSql('ALTER TABLE blog_post ADD is_pinned TINYINT DEFAULT 0 NOT NULL, ADD is_locked_comments TINYINT DEFAULT 0 NOT NULL, ADD is_answered TINYINT DEFAULT 0 NOT NULL, ADD is_highlighted TINYINT DEFAULT 0 NOT NULL, ADD moderation_status VARCHAR(20) DEFAULT \'approved\' NOT NULL, CHANGE status status ENUM(\'draft\', \'published\', \'archived\') NOT NULL');
        $this->addSql('DROP INDEX uniq_ba5ae01d989d9b62 ON blog_post');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BLOG_POST_SLUG ON blog_post (slug)');
        $this->addSql('DROP INDEX idx_ba5ae01df675f31b ON blog_post');
        $this->addSql('CREATE INDEX IDX_BLOG_POST_AUTHOR ON blog_post (author_id)');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT FK_BA5AE01DF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_interaction DROP FOREIGN KEY FK_64A75721A76ED395');
        $this->addSql('ALTER TABLE content_interaction CHANGE comment_text comment_text TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_INTERACTION_TARGET ON content_interaction (target_type, target_id)');
        $this->addSql('DROP INDEX idx_64a75721a76ed395 ON content_interaction');
        $this->addSql('CREATE INDEX IDX_INTERACTION_USER ON content_interaction (user_id)');
        $this->addSql('ALTER TABLE content_interaction ADD CONSTRAINT FK_64A75721A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_message DROP FOREIGN KEY FK_317DA4E49AC0396');
        $this->addSql('ALTER TABLE dm_message DROP FOREIGN KEY FK_317DA4E4F624B39D');
        $this->addSql('ALTER TABLE dm_message CHANGE status status VARCHAR(20) DEFAULT \'sent\' NOT NULL, CHANGE attachment_mime attachment_mime VARCHAR(100) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_317da4e49ac0396 ON dm_message');
        $this->addSql('CREATE INDEX IDX_DM_MESSAGE_CONV ON dm_message (conversation_id)');
        $this->addSql('DROP INDEX idx_317da4e4f624b39d ON dm_message');
        $this->addSql('CREATE INDEX IDX_DM_MESSAGE_SENDER ON dm_message (sender_id)');
        $this->addSql('ALTER TABLE dm_message ADD CONSTRAINT FK_317DA4E49AC0396 FOREIGN KEY (conversation_id) REFERENCES dm_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_message ADD CONSTRAINT FK_317DA4E4F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_participant DROP FOREIGN KEY FK_FFE2DC559AC0396');
        $this->addSql('ALTER TABLE dm_participant DROP FOREIGN KEY FK_FFE2DC55A76ED395');
        $this->addSql('DROP INDEX idx_ffe2dc559ac0396 ON dm_participant');
        $this->addSql('CREATE INDEX IDX_DM_PARTICIPANT_CONV ON dm_participant (conversation_id)');
        $this->addSql('DROP INDEX idx_ffe2dc55a76ed395 ON dm_participant');
        $this->addSql('CREATE INDEX IDX_DM_PARTICIPANT_USER ON dm_participant (user_id)');
        $this->addSql('ALTER TABLE dm_participant ADD CONSTRAINT FK_FFE2DC559AC0396 FOREIGN KEY (conversation_id) REFERENCES dm_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_participant ADD CONSTRAINT FK_FFE2DC55A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE events CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE fitness_exercise DROP FOREIGN KEY FK_19AD82A9A76ED395');
        $this->addSql('ALTER TABLE fitness_exercise CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_19ad82a9a76ed395 ON fitness_exercise');
        $this->addSql('CREATE INDEX IDX_EA2A2DEAA76ED395 ON fitness_exercise (user_id)');
        $this->addSql('ALTER TABLE fitness_exercise ADD CONSTRAINT FK_19AD82A9A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fitness_program DROP FOREIGN KEY FK_58E6F9FAA76ED395');
        $this->addSql('ALTER TABLE fitness_program CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_58e6f9faa76ed395 ON fitness_program');
        $this->addSql('CREATE INDEX IDX_8FE92485A76ED395 ON fitness_program (user_id)');
        $this->addSql('ALTER TABLE fitness_program ADD CONSTRAINT FK_58E6F9FAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fitness_program_exercise DROP FOREIGN KEY FK_E34BCFAE52052ED7');
        $this->addSql('ALTER TABLE fitness_program_exercise DROP FOREIGN KEY FK_E34BCFAE113A865A');
        $this->addSql('DROP INDEX idx_e34bcfae52052ed7 ON fitness_program_exercise');
        $this->addSql('CREATE INDEX IDX_C86E7B4586A3EC95 ON fitness_program_exercise (fitness_program_id)');
        $this->addSql('DROP INDEX idx_e34bcfae113a865a ON fitness_program_exercise');
        $this->addSql('CREATE INDEX IDX_C86E7B45B2A9046A ON fitness_program_exercise (fitness_exercise_id)');
        $this->addSql('ALTER TABLE fitness_program_exercise ADD CONSTRAINT FK_E34BCFAE52052ED7 FOREIGN KEY (fitness_program_id) REFERENCES fitness_program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fitness_program_exercise ADD CONSTRAINT FK_E34BCFAE113A865A FOREIGN KEY (fitness_exercise_id) REFERENCES fitness_exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_thread DROP FOREIGN KEY FK_298F7F52A76ED395');
        $this->addSql('ALTER TABLE forum_thread CHANGE status status ENUM(\'open\', \'closed\', \'pinned\') NOT NULL');
        $this->addSql('DROP INDEX idx_298f7f52a76ed395 ON forum_thread');
        $this->addSql('CREATE INDEX IDX_FORUM_THREAD_AUTHOR ON forum_thread (user_id)');
        $this->addSql('ALTER TABLE forum_thread ADD CONSTRAINT FK_298F7F52A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_f5299398551f0f81 ON `order`');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F52993988D9F6D38 ON `order` (order_number)');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F097793FA21');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT `FK_52EA1F099D8F7F82` FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_52ea1f098d9f6d38 ON order_item');
        $this->addSql('CREATE INDEX IDX_52EA1F099D8F7F82 ON order_item (order_id)');
        $this->addSql('DROP INDEX idx_52ea1f097793fa21 ON order_item');
        $this->addSql('CREATE INDEX IDX_52EA1F09B6B7EA44 ON order_item (supplement_id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F097793FA21 FOREIGN KEY (supplement_id) REFERENCES supplement (id)');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FD52B4B97');
        $this->addSql('ALTER TABLE participation CHANGE date_inscription date_inscription DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_ab55e24fd52b4b97 ON participation');
        $this->addSql('CREATE INDEX IDX_AB55E24A3FB41356 ON participation (id_event)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FD52B4B97 FOREIGN KEY (id_event) REFERENCES events (id_event) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE regime_alimentaire DROP FOREIGN KEY FK_58CC75A6A76ED395');
        $this->addSql('DROP INDEX idx_58cc75a6a76ed395 ON regime_alimentaire');
        $this->addSql('CREATE INDEX IDX_REGIME_USER ON regime_alimentaire (user_id)');
        $this->addSql('ALTER TABLE regime_alimentaire ADD CONSTRAINT FK_58CC75A6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY FK_A8D351B3A76ED395');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY FK_A8D351B335E7D534');
        $this->addSql('DROP INDEX idx_a8d351b3a76ed395 ON repas');
        $this->addSql('CREATE INDEX IDX_REPAS_USER ON repas (user_id)');
        $this->addSql('DROP INDEX idx_a8d351b335e7d534 ON repas');
        $this->addSql('CREATE INDEX IDX_REPAS_REGIME ON repas (regime_id)');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT FK_A8D351B3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT FK_A8D351B335E7D534 FOREIGN KEY (regime_id) REFERENCES regime_alimentaire (id_regime) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE supplement_review DROP FOREIGN KEY FK_9E5C737D7793FA21');
        $this->addSql('DROP INDEX idx_9e5c737d7793fa21 ON supplement_review');
        $this->addSql('CREATE INDEX IDX_A4D1EA4FB6B7EA44 ON supplement_review (supplement_id)');
        $this->addSql('ALTER TABLE supplement_review ADD CONSTRAINT FK_9E5C737D7793FA21 FOREIGN KEY (supplement_id) REFERENCES supplement (id) ON DELETE CASCADE');
    }
}
