-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 24 fév. 2026 à 17:21
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `symfony_project`
--

-- --------------------------------------------------------

--
-- Structure de la table `blog_post`
--

CREATE TABLE `blog_post` (
  `id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `content` longtext NOT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked_comments` tinyint(1) NOT NULL DEFAULT 0,
  `is_answered` tinyint(1) NOT NULL DEFAULT 0,
  `is_highlighted` tinyint(1) NOT NULL DEFAULT 0,
  `moderation_status` varchar(20) NOT NULL DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `blog_post`
--

INSERT INTO `blog_post` (`id`, `author_id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `status`, `category`, `view_count`, `created_at`, `updated_at`, `published_at`, `is_pinned`, `is_locked_comments`, `is_answered`, `is_highlighted`, `moderation_status`) VALUES
(3, 5, 'fffff', 'fffff-b4b376', 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff', 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff', 'coach-1-6982d981df438.jpg', 'published', 'Entrainement', 0, '2026-02-04 06:30:41', '2026-02-04 06:48:59', '2026-02-04 06:30:41', 0, 0, 0, 0, 'approved');

-- --------------------------------------------------------

--
-- Structure de la table `content_interaction`
--

CREATE TABLE `content_interaction` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target_type` varchar(20) NOT NULL,
  `target_id` int(11) NOT NULL,
  `interaction_type` varchar(20) NOT NULL,
  `comment_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `content_interaction`
--

INSERT INTO `content_interaction` (`id`, `user_id`, `target_type`, `target_id`, `interaction_type`, `comment_text`, `created_at`) VALUES
(3, 8, 'blog_post', 3, 'comment', 'ddddd', '2026-02-05 01:08:54'),
(5, 8, 'blog_post', 3, 'like', NULL, '2026-02-05 01:14:16'),
(7, 8, 'blog_post', 1, 'like', NULL, '2026-02-05 01:14:28'),
(8, 8, 'blog_post', 3, 'repost', NULL, '2026-02-05 14:36:44'),
(10, 10, 'blog_post', 3, 'like', NULL, '2026-02-11 11:07:51'),
(11, 10, 'blog_post', 3, 'repost', NULL, '2026-02-11 11:07:59');

-- --------------------------------------------------------

--
-- Structure de la table `dm_conversation`
--

CREATE TABLE `dm_conversation` (
  `id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `last_message_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `dm_conversation`
--

INSERT INTO `dm_conversation` (`id`, `created_at`, `last_message_at`) VALUES
(1, '2026-02-05 14:31:20', '2026-02-05 14:58:24'),
(2, '2026-02-05 14:43:29', '2026-02-05 14:59:28'),
(3, '2026-02-05 14:43:38', NULL),
(4, '2026-02-05 14:51:17', NULL),
(5, '2026-02-05 15:18:50', NULL),
(6, '2026-02-05 15:24:38', NULL),
(7, '2026-02-05 15:33:59', '2026-02-11 11:11:17'),
(8, '2026-02-08 12:40:46', NULL),
(9, '2026-02-09 22:48:35', '2026-02-10 09:30:57');

-- --------------------------------------------------------

--
-- Structure de la table `dm_message`
--

CREATE TABLE `dm_message` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` longtext NOT NULL,
  `created_at` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'sent',
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `dm_message`
--

INSERT INTO `dm_message` (`id`, `conversation_id`, `sender_id`, `body`, `created_at`, `status`, `attachment_path`, `attachment_mime`) VALUES
(1, 1, 8, 'hello', '2026-02-05 14:31:35', 'sent', NULL, NULL),
(2, 1, 8, 'how are you', '2026-02-05 14:35:27', 'sent', NULL, NULL),
(3, 1, 8, 'wink', '2026-02-05 14:58:24', 'sent', NULL, NULL),
(4, 2, 8, 'hello', '2026-02-05 14:59:28', 'sent', NULL, NULL),
(5, 7, 10, 'hello', '2026-02-05 15:34:06', 'read', NULL, NULL),
(6, 7, 8, 'hey', '2026-02-05 15:34:33', 'read', NULL, NULL),
(7, 7, 8, 'how are you', '2026-02-05 15:45:45', 'read', NULL, NULL),
(8, 7, 10, 'fine', '2026-02-05 15:47:03', 'read', NULL, NULL),
(9, 7, 10, 'winek', '2026-02-05 15:51:40', 'read', NULL, NULL),
(10, 7, 8, 'hani', '2026-02-05 15:52:09', 'read', NULL, NULL),
(11, 7, 8, 'hobi wink rawaht', '2026-02-05 19:32:37', 'read', NULL, NULL),
(12, 7, 10, 'ay ani mazelt ki rawaht', '2026-02-05 19:33:52', 'read', NULL, NULL),
(13, 7, 10, 'halhoul', '2026-02-05 20:03:42', 'read', NULL, NULL),
(14, 7, 8, 'salut', '2026-02-06 19:13:42', 'read', NULL, NULL),
(15, 7, 10, 'cvn', '2026-02-06 19:14:20', 'read', NULL, NULL),
(16, 7, 8, 'oui hamdoulah', '2026-02-07 13:17:11', 'read', NULL, NULL),
(17, 7, 8, 'hello', '2026-02-08 12:37:35', 'read', NULL, NULL),
(18, 7, 10, 'hi', '2026-02-08 12:50:48', 'read', NULL, NULL),
(19, 9, 11, 'ahla dhia', '2026-02-09 22:48:48', 'read', NULL, NULL),
(20, 9, 10, 'cvn', '2026-02-09 22:49:28', 'read', NULL, NULL),
(21, 9, 11, 'cv et toi', '2026-02-10 09:30:57', 'sent', NULL, NULL),
(22, 7, 10, 'test', '2026-02-11 11:11:17', 'sent', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `dm_participant`
--

CREATE TABLE `dm_participant` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read_at` datetime DEFAULT NULL,
  `typing_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `dm_participant`
--

INSERT INTO `dm_participant` (`id`, `conversation_id`, `user_id`, `last_read_at`, `typing_at`) VALUES
(1, 1, 8, '2026-02-05 15:18:42', NULL),
(3, 2, 8, '2026-02-06 19:21:55', NULL),
(4, 2, 7, NULL, NULL),
(5, 3, 8, '2026-02-08 13:24:03', NULL),
(6, 3, 5, NULL, NULL),
(7, 4, 8, '2026-02-05 14:51:18', NULL),
(8, 4, 1, NULL, NULL),
(9, 5, 8, '2026-02-05 15:18:51', NULL),
(10, 5, 6, NULL, NULL),
(11, 6, 8, '2026-02-08 13:21:39', NULL),
(12, 6, 4, NULL, NULL),
(13, 7, 10, '2026-02-11 11:11:17', '2026-02-08 12:50:46'),
(14, 7, 8, '2026-02-11 11:12:10', '2026-02-08 12:37:35'),
(15, 8, 10, '2026-02-11 05:40:13', NULL),
(16, 8, 7, NULL, NULL),
(17, 9, 11, '2026-02-10 09:31:05', '2026-02-10 09:30:57'),
(18, 9, 10, '2026-02-11 10:17:13', '2026-02-09 22:49:28');

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260204002835', '2026-02-16 12:01:44', 2),
('DoctrineMigrations\\Version20260215215927', '2026-02-16 12:01:44', 35),
('DoctrineMigrations\\Version20260216130000', '2026-02-16 13:20:57', 93),
('DoctrineMigrations\\Version20260216131000', '2026-02-16 13:20:57', 429),
('DoctrineMigrations\\Version20260216143000', '2026-02-16 13:07:34', 46);

-- --------------------------------------------------------

--
-- Structure de la table `events`
--

CREATE TABLE `events` (
  `id_event` int(11) NOT NULL,
  `titre` varchar(150) NOT NULL,
  `description` longtext NOT NULL,
  `date_event` date NOT NULL,
  `lieu` varchar(150) NOT NULL,
  `capacite` int(11) NOT NULL,
  `type_event` varchar(100) NOT NULL,
  `image_event` varchar(255) DEFAULT NULL,
  `prix_event` decimal(10,2) NOT NULL,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `events`
--

INSERT INTO `events` (`id_event`, `titre`, `description`, `date_event`, `lieu`, `capacite`, `type_event`, `image_event`, `prix_event`, `created_at`) VALUES
(2, 'Atelier Nutrition', 'Workshop sur la nutrition sportive et les bonnes pratiques alimentaires.', '2026-03-15', 'Salle Fitopia', 30, 'Nutrition', 'nutrition.jpg', 0.00, '2026-02-11 07:17:18'),
(3, 'Challenge Cardio', 'Challenge collectif pour booster le cardio et l\'endurance.', '2026-04-02', 'Studio Fitopia', 25, 'Sport', 'cardio.jpg', 25.00, '2026-02-11 07:17:18');

-- --------------------------------------------------------

--
-- Structure de la table `fitness_exercise`
--

CREATE TABLE `fitness_exercise` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `muscle_group` varchar(100) NOT NULL,
  `difficulty` varchar(50) NOT NULL,
  `sets` int(11) DEFAULT NULL,
  `repetitions` int(11) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fitness_exercise`
--

INSERT INTO `fitness_exercise` (`id`, `name`, `description`, `muscle_group`, `difficulty`, `sets`, `repetitions`, `video_url`, `image_url`, `duration`, `created_at`, `updated_at`, `user_id`) VALUES
(2, 'SSS', '333333', '3333', 'zzzzzzzz', 66, 333, NULL, NULL, 14, '2026-02-11 09:42:13', '2026-02-11 09:42:13', 7);

-- --------------------------------------------------------

--
-- Structure de la table `fitness_program`
--

CREATE TABLE `fitness_program` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `level` varchar(50) NOT NULL,
  `duration_weeks` int(11) NOT NULL,
  `sessions_per_week` int(11) NOT NULL,
  `session_duration` int(11) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fitness_program`
--

INSERT INTO `fitness_program` (`id`, `title`, `description`, `category`, `level`, `duration_weeks`, `sessions_per_week`, `session_duration`, `image_url`, `video_url`, `is_public`, `created_at`, `updated_at`, `user_id`) VALUES
(1, 'ppl', 'ssssss', 'ranya', 'hjssxx', 4, 3, 45, NULL, NULL, 1, '2026-02-11 09:37:12', '2026-02-11 09:42:13', NULL),
(2, 'aaa', 'aaaaaaa', 'aaa', 'hjssxx', 4, 3, 45, NULL, NULL, 1, '2026-02-11 09:41:25', '2026-02-11 09:41:25', 4),
(3, 'poitrine', NULL, 'fat loss', 'expert', 4, 2, 10, NULL, NULL, 1, '2026-02-11 11:20:18', '2026-02-11 11:20:18', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `fitness_program_exercise`
--

CREATE TABLE `fitness_program_exercise` (
  `fitness_program_id` int(11) NOT NULL,
  `fitness_exercise_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fitness_program_exercise`
--

INSERT INTO `fitness_program_exercise` (`fitness_program_id`, `fitness_exercise_id`) VALUES
(1, 2);

-- --------------------------------------------------------

--
-- Structure de la table `forum_thread`
--

CREATE TABLE `forum_thread` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` longtext NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('open','closed','pinned') NOT NULL,
  `is_solved` tinyint(1) NOT NULL DEFAULT 0,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `reply_count` int(11) NOT NULL DEFAULT 0,
  `last_activity` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `order`
--

CREATE TABLE `order` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `discount_code` varchar(50) DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `order`
--

INSERT INTO `order` (`id`, `order_number`, `first_name`, `last_name`, `email`, `phone`, `address`, `city`, `postal_code`, `payment_method`, `status`, `subtotal`, `shipping`, `discount`, `total`, `discount_code`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'ORD-698C3D41A152F', 'dhia', 'selmi', 'dhia@gmail.com', '50633178', 'khzema', 'sousse', '3080', 'mastercard', 'pending', 20.00, 7.00, 0.00, 27.00, NULL, 'aaaaaaa', '2026-02-11 09:26:41', '2026-02-11 09:26:41'),
(2, 'ORD-698C3DE9C53D2', 'mohamed', 'movenpic', 'medomarselmi@gmail.TN', '58936689', 'khzema', 'sousse', '3080', 'visa', 'pending', 20.00, 7.00, 0.00, 27.00, NULL, 'FZEREZ', '2026-02-11 09:29:29', '2026-02-11 09:29:29'),
(3, 'ORD-698C5101926E5', 'ali', 'touaiti', 'ali@gmail.com', '53919881', 'ariana', 'ariana', '2045', 'visa', 'pending', 125.00, 0.00, 0.00, 125.00, NULL, 'eeeee', '2026-02-11 10:50:57', '2026-02-11 10:50:57');

-- --------------------------------------------------------

--
-- Structure de la table `order_item`
--

CREATE TABLE `order_item` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `supplement_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `order_item`
--

INSERT INTO `order_item` (`id`, `order_id`, `supplement_id`, `quantity`, `price`, `total`) VALUES
(1, 1, 1, 1, 20.00, 20.00),
(2, 2, 1, 1, 20.00, 20.00),
(3, 3, 3, 1, 125.00, 125.00);

-- --------------------------------------------------------

--
-- Structure de la table `participation`
--

CREATE TABLE `participation` (
  `id_participation` int(11) NOT NULL,
  `id_event` int(11) NOT NULL,
  `nom_participant` varchar(150) NOT NULL,
  `email_participant` varchar(150) NOT NULL,
  `date_inscription` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `participation`
--

INSERT INTO `participation` (`id_participation`, `id_event`, `nom_participant`, `email_participant`, `date_inscription`) VALUES
(1, 2, 'SSSSSSSSS', 'medomarselmi@gmail.TN', '2026-02-11 09:35:02'),
(2, 3, 'SSSSSSSSS', 'medomarselmi@gmail.TN', '2026-02-11 10:26:40'),
(3, 2, 'sarah jrd', 'sarah.jardak@gmail.com', '2026-02-11 11:26:25');

-- --------------------------------------------------------

--
-- Structure de la table `regime_alimentaire`
--

CREATE TABLE `regime_alimentaire` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `taille` decimal(5,2) DEFAULT NULL,
  `poids` decimal(5,2) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `type_sante` varchar(50) DEFAULT NULL,
  `calories_cibles` int(11) DEFAULT NULL,
  `repas_adequats` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `regime_alimentaire`
--

INSERT INTO `regime_alimentaire` (`id`, `user_id`, `taille`, `poids`, `age`, `bmi`, `type_sante`, `calories_cibles`, `repas_adequats`) VALUES
(2, 4, 170.00, 65.00, 25, 22.50, 'Normal', 2000, 'Exemple de repas'),
(3, 10, 172.00, 125.00, 25, 42.25, 'obesite', 2381, 'Régime hypocalorique contrôlé. Légumes, viandes blanches, limitation des féculents et graisses.'),
(4, 10, 168.00, 40.00, 28, 14.17, 'sous_poids', 1736, 'Augmentez les calories, protéines et glucides. Repas riches: avocat, noix, viandes, œufs, riz complet.'),
(8, 10, 260.00, 350.00, 50, 51.78, 'obesite', 5270, 'Régime hypocalorique contrôlé. Légumes, viandes blanches, limitation des féculents et graisses.'),
(9, 10, 164.00, 180.00, 25, 66.92, 'obesite', 2921, 'Régime hypocalorique contrôlé. Légumes, viandes blanches, limitation des féculents et graisses.'),
(10, 10, 180.00, 50.00, 30, 15.43, 'sous_poids', 1954, 'Augmentez les calories, protéines et glucides. Repas riches: avocat, noix, viandes, œufs, riz complet.'),
(11, 10, 120.00, 30.00, 11, 20.83, 'normal', 1200, 'Équilibrez protéines, glucides et lipides. Privilégiez légumes, viandes maigres, céréales complètes.'),
(12, 10, 100.00, 25.00, 10, 25.00, 'surpoids', 896, 'Réduisez les calories, évitez sucres raffinés. Légumes verts, protéines maigres, glucides complexes.'),
(13, 10, 100.00, 25.00, 11, 25.00, 'surpoids', 891, 'Réduisez les calories, évitez sucres raffinés. Légumes verts, protéines maigres, glucides complexes.');

-- --------------------------------------------------------

--
-- Structure de la table `repas`
--

CREATE TABLE `repas` (
  `id_repas` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_repas` datetime NOT NULL,
  `type_repas` varchar(50) NOT NULL,
  `nom_repas` varchar(150) NOT NULL,
  `calories` int(11) DEFAULT NULL,
  `proteines` int(11) DEFAULT NULL,
  `glucides` int(11) DEFAULT NULL,
  `lipides` int(11) DEFAULT NULL,
  `commentaire` longtext DEFAULT NULL,
  `regime_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `repas`
--

INSERT INTO `repas` (`id_repas`, `user_id`, `date_repas`, `type_repas`, `nom_repas`, `calories`, `proteines`, `glucides`, `lipides`, `commentaire`, `regime_id`) VALUES
(15, 10, '2026-02-23 14:53:00', 'evening', 'crepe', 300, 21, 55, 500, NULL, NULL),
(26, 10, '2026-02-28 21:26:00', 'breakfast', 'tiramisu', 200, 100, 200, 20, NULL, 4),
(27, 10, '2026-02-26 21:26:00', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 4),
(28, 10, '2026-02-20 21:26:00', 'breakfast', 'pizza', 200, 100, 200, 20, NULL, 4),
(29, 10, '2026-02-17 21:26:40', 'breakfast', 'salad', 200, 100, 200, 20, NULL, 4),
(31, 10, '2026-02-18 09:07:10', 'evening', 'chicken', 1, 21, 55, 500, NULL, 9),
(32, 10, '2026-02-18 09:24:41', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 9),
(33, 10, '2026-02-18 09:24:45', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 9),
(34, 10, '2026-02-18 09:24:49', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 9),
(35, 10, '2026-02-18 09:24:53', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 9),
(36, 10, '2026-02-18 09:24:57', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 9),
(37, 10, '2026-02-18 09:44:34', 'breakfast', 'pizza', 200, 100, 200, 20, NULL, 9),
(38, 10, '2026-02-18 09:44:39', 'evening', 'crepe', 300, 21, 55, 500, NULL, 9),
(39, 10, '2026-02-18 09:44:44', 'evening', 'crepe', 300, 21, 55, 500, NULL, 9),
(40, 10, '2026-02-18 09:44:48', 'evening', 'crepe', 300, 21, 55, 500, NULL, 9),
(41, 10, '2026-02-18 09:44:52', 'evening', 'crepe', 300, 21, 55, 500, NULL, 9),
(42, 10, '2026-02-18 09:44:58', 'evening', 'crepe', 300, 21, 55, 500, NULL, 9),
(43, 10, '2026-02-18 22:31:51', 'evening', 'chicken', 52, 21, 55, 500, NULL, 10),
(44, 10, '2026-02-18 22:31:56', 'evening', 'crepe', 300, 21, 55, 500, NULL, 10),
(45, 10, '2026-02-18 22:32:03', 'evening', 'crepe', 300, 21, 55, 500, NULL, 10),
(46, 10, '2026-02-18 22:32:11', 'evening', 'crepe', 300, 21, 55, 500, NULL, 10),
(47, 10, '2026-02-18 22:32:22', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 10),
(48, 10, '2026-02-18 22:32:27', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 10),
(49, 10, '2026-02-18 22:32:32', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 10),
(50, 10, '2026-02-18 22:32:38', 'evening', 'chicken', 1, 21, 55, 500, NULL, 10),
(51, 10, '2026-02-18 22:32:45', 'evening', 'crepe', 200, 21, 55, 500, NULL, 10),
(52, 10, '2026-02-18 22:50:32', 'breakfast', 'pizza', 200, 100, 200, 20, NULL, 10),
(53, 10, '2026-02-18 22:51:12', 'evening', 'chicken', 1, 21, 55, 500, NULL, 10),
(54, 10, '2026-02-18 22:59:50', 'evening', 'crepe', 300, 21, 55, 500, NULL, 3),
(55, 10, '2026-02-18 22:59:58', 'evening', 'crepe', 300, 21, 55, 500, NULL, 3),
(56, 10, '2026-02-18 23:00:04', 'evening', 'crepe', 300, 21, 55, 500, NULL, 3),
(57, 10, '2026-02-18 23:00:09', 'evening', 'crepe', 300, 21, 55, 500, NULL, 3),
(58, 10, '2026-02-18 23:00:14', 'evening', 'chicken', 1, 21, 55, 500, NULL, 3),
(59, 10, '2026-02-18 23:00:23', 'evening', 'crepe', 300, 21, 55, 500, NULL, 3),
(60, 10, '2026-02-18 23:00:30', 'evening', 'crepe', 300, 21, 55, 500, NULL, 3),
(61, 10, '2026-02-18 23:00:36', 'evening', 'crepe', 300, 21, 55, 500, NULL, 3),
(62, 10, '2026-02-18 23:00:44', 'evening', 'chicken', 79, 21, 55, 500, NULL, 3),
(63, 10, '2026-02-18 23:00:57', 'breakfast', 'pate', 200, 100, 200, 20, NULL, 3),
(64, 10, '2026-02-18 23:11:19', 'evening', 'chicken', 1, 21, 55, 500, NULL, 3),
(65, 10, '2026-02-18 23:13:30', 'evening', 'crepe', 300, 21, 55, 500, NULL, 11),
(66, 10, '2026-02-18 23:13:37', 'evening', 'crepe', 300, 21, 55, 500, NULL, 11),
(67, 10, '2026-02-18 23:13:43', 'evening', 'crepe', 300, 21, 55, 500, NULL, 11),
(68, 10, '2026-02-18 23:13:49', 'evening', 'crepe', 300, 21, 55, 500, NULL, 11),
(69, 10, '2026-02-18 23:15:51', 'evening', 'crepe', 300, 21, 55, 500, NULL, 12),
(70, 10, '2026-02-18 23:15:56', 'evening', 'crepe', 300, 21, 55, 500, NULL, 12),
(71, 10, '2026-02-18 23:16:05', 'evening', 'crepe', 200, 21, 55, 500, NULL, 12),
(72, 10, '2026-02-18 23:16:31', 'evening', 'chicken', 79, 21, 55, 500, NULL, 12),
(73, 10, '2026-02-18 23:16:43', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(74, 10, '2026-02-18 23:16:47', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(75, 10, '2026-02-18 23:16:52', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(76, 10, '2026-02-18 23:16:56', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(77, 10, '2026-02-18 23:17:01', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(78, 10, '2026-02-18 23:17:05', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(79, 10, '2026-02-18 23:17:12', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(80, 10, '2026-02-18 23:17:17', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(81, 10, '2026-02-18 23:17:21', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(82, 10, '2026-02-18 23:17:26', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(83, 10, '2026-02-18 23:17:31', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(84, 10, '2026-02-18 23:17:36', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(85, 10, '2026-02-18 23:17:40', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(86, 10, '2026-02-18 23:17:44', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(87, 10, '2026-02-18 23:17:47', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(88, 10, '2026-02-18 23:17:52', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(89, 10, '2026-02-18 23:17:55', 'evening', 'chicken', 1, 21, 55, 500, NULL, 12),
(90, 10, '2026-02-18 23:20:42', 'evening', 'crepe', 200, 21, 55, 500, NULL, 13),
(91, 10, '2026-02-18 23:20:52', 'breakfast', 'salad', 200, 100, 200, 20, NULL, 13),
(92, 10, '2026-02-18 23:21:01', 'breakfast', 'pizza', 290, 100, 200, 20, NULL, 13),
(93, 10, '2026-02-18 23:21:11', 'breakfast', 'salad', 200, 100, 200, 20, NULL, 13),
(94, 10, '2026-02-18 23:21:55', 'evening', 'chicken', 1, 21, 55, 500, NULL, 13),
(95, 10, '2026-02-23 12:56:36', 'Dejeuner', 'greek_salad', 239, 7, 6, 21, 'Confiance IA: 98%. Source: photo analysee automatiquement.', 13),
(96, 10, '2026-02-23 13:08:13', 'Dejeuner', 'french_fries', 339, 6, 55, 13, 'Confiance IA: 99.5%. Source: photo analysee automatiquement.', 13),
(97, 10, '2026-02-23 13:11:55', 'evening', 'chicken', 77, 21, 55, 500, NULL, 13),
(98, 8, '2026-02-28 13:14:00', 'evening', 'fritep', 120, 150, 160, 170, NULL, NULL),
(99, 11, '2026-02-27 13:16:00', 'collat', 'tiramisum', 122, 124, 154, 200, NULL, 12),
(101, 10, '2026-02-24 15:51:43', 'Dejeuner', 'lasagna', 662, 25, 122, 7, 'Confiance IA: 99.3%. Source: photo analysee automatiquement.', 13),
(102, 10, '2026-02-24 15:57:59', 'evening', 'chicken', 52, 21, 55, 500, NULL, 13),
(103, 10, '2026-02-24 15:58:10', 'evening', 'chicken', 52, 21, 55, 500, NULL, 13),
(104, 10, '2026-02-24 15:58:23', 'evening', 'chicken', 52, 21, 55, 500, NULL, 13);

-- --------------------------------------------------------

--
-- Structure de la table `supplement`
--

CREATE TABLE `supplement` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL,
  `calories` int(11) DEFAULT NULL,
  `description` longtext NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `supplement`
--

INSERT INTO `supplement` (`id`, `name`, `category`, `brand`, `price`, `stock`, `calories`, `description`, `image`, `created_at`, `updated_at`) VALUES
(1, 'creatine', 'Creatine', 'MuscleTech', 20.00, 100, 2555, 'goood', 'n-698bece7d73db.jpg', '2026-02-11 03:43:51', '2026-02-11 03:50:41'),
(2, 'proteine', 'prot', 'gsn', 125.00, 50, 22, 'proteine ', NULL, '2026-02-11 10:48:41', '2026-02-11 10:48:41'),
(3, 'proteine12', 'prot', 'gsn', 125.00, 50, 22, 'alalal', 'n-698c50a2a55ff.jpg', '2026-02-11 10:49:22', '2026-02-11 10:49:22');

-- --------------------------------------------------------

--
-- Structure de la table `supplement_review`
--

CREATE TABLE `supplement_review` (
  `id` int(11) NOT NULL,
  `supplement_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(180) DEFAULT NULL,
  `rating` smallint(6) NOT NULL,
  `comment` longtext NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `supplement_review`
--

INSERT INTO `supplement_review` (`id`, `supplement_id`, `name`, `email`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 'dhia00', 'dhia@gmail.com', 5, 'lol', '2026-02-11 05:07:07'),
(2, 1, 'dhia00', 'dhia@gmail.com', 4, 'ff', '2026-02-11 05:16:01'),
(3, 1, 'test', 'test@gmail.com', 5, 'bien', '2026-02-11 10:55:03'),
(4, 1, 'test', 'test@gmail.com', 3, 'ff', '2026-02-11 10:55:08'),
(5, 1, 'test', 'test@gmail.com', 1, 'a', '2026-02-11 10:55:19');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(180) NOT NULL,
  `username` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `birth_date` date NOT NULL,
  `gender` varchar(10) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) NOT NULL,
  `height` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `target_weight` double DEFAULT NULL,
  `fitness_level` varchar(255) DEFAULT NULL,
  `health_conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`health_conditions`)),
  `dietary_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dietary_preferences`)),
  `fitness_goals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fitness_goals`)),
  `professional_title` varchar(255) DEFAULT NULL,
  `specialization` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specialization`)),
  `qualification` varchar(255) DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT NULL,
  `bio` longtext DEFAULT NULL,
  `license_number` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `birth_date`, `gender`, `avatar`, `roles`, `password`, `height`, `weight`, `target_weight`, `fitness_level`, `health_conditions`, `dietary_preferences`, `fitness_goals`, `professional_title`, `specialization`, `qualification`, `years_of_experience`, `bio`, `license_number`) VALUES
(1, 'alaeddine.lefi@esprit.tn', 'omar21', 'mohamed', 'selmi', '29703449', '2026-02-06', 'male', NULL, '[\"ROLE_USER\"]', '$2y$13$tftdjitIIi.3SGTVSxNA.unHTzxzFMnF7gnosusNjsmQDgEjW6/ii', 166, 76, 88, 'high', '[\"good\"]', '[\"ssss\"]', '[\"sssssss\"]', 'zdazdadza', '[\"ssssssssss\"]', 'azdazdazd', 5647654, 'ssssssssss', '99999'),
(4, 'emna@gmail.com', 'emna', 'emna', 'benhsine', '22222222', '2026-02-24', 'male', 'coach-1-69829d5a2ce2e.jpg', '[\"ROLE_ADMIN\"]', '$2y$13$taCIG8neKcnrBAyzwbm.sOD1iw4VeEqCbJpR.UDUizojX2R5kKMLG', 166, 76, 88, 'advanced', '[\"iiiiiiiiiiiiiiiiii\"]', '[\"vegetarian\"]', '[\"endurance\"]', NULL, '[]', NULL, NULL, NULL, NULL),
(5, 'ahmed@gmail.com', 'toto', 'ahmed', 'chebbi', '29703449', '2026-02-08', 'male', 'n-6982dc744fc6f.jpg', '[\"ROLE_ADMIN\"]', '$2y$13$RPkrDesg5YIG../HO4/d.ejEc9GCkSXozc4KyiQ9c114Qt1qazNTi', NULL, NULL, NULL, NULL, '[]', '[]', '[]', NULL, '[]', NULL, NULL, NULL, NULL),
(6, 'oumar@gmail.com', 'lolo', 'omar', 'lefi', '29703449', '2026-02-26', 'male', 'logo-6982a69a4126d.png', '[\"ROLE_ADMIN\"]', '$2y$13$cx0BOM5RZo9xum9OpDqoMOG3BA78qAiG73cjJikBNETpD/aet11I6', 166, 76, 88, 'beginner', '[\"ddddddd\"]', '[\"vegetarian\"]', '[\"weight_loss\"]', NULL, '[]', NULL, NULL, NULL, NULL),
(7, 'nizar@gmail.com', 'ni', 'nizar', 'dh', '00000000', '2026-02-14', 'male', 'logo-6982a77dd2ee7.png', '[\"ROLE_PATIENT\"]', '$2y$13$nm9a/wkxXKpYCLhAoiYC5ui.luUX2hADYVYwyqDcFhfaA0qaLnp0W', 166, 76, 88, 'beginner', '[\"ddddddddd\"]', '[\"vegetarian\"]', '[\"endurance\"]', NULL, '[]', NULL, NULL, NULL, NULL),
(8, 'oumay@gmail.com', 'bh', 'oumayma', 'bahri', '555555555', '2026-02-25', 'female', 'diagram2-6982a89678d5a.png', '[\"ROLE_PATIENT\"]', '$2y$13$Qj7HRR6g79yGLPd/usU5L.vO4VC3K3alqXmFeGSs3vpg7Ur0JoW9m', 166, 76, 88, 'intermediate', '[\"EEEEEE\"]', '[\"vegetarian\",\"halal\",\"keto\"]', '[\"endurance\",\"maintenance\"]', NULL, '[]', NULL, NULL, NULL, NULL),
(10, 'dhia@gmail.com', 'dhia00', 'dhia', 'selmi', '22222222', '2026-02-27', 'male', 'emna-6984aa4a0c0a7.png', '[\"ROLE_PATIENT\"]', '$2y$13$eqjheGOhcGYg2jKzHdBSUOTS3UyOoIcn2mSsNTeDleWwrihiGxA6.', 166, 225, 88, 'advanced', '[\"goood\"]', '[\"vegetarian\"]', '[\"muscle_gain\"]', NULL, '[]', NULL, NULL, NULL, NULL),
(11, 'ranya@gmail.com', 'ranya@gmail.com', 'ranya', 'landolsi', '58936689', '2026-02-01', 'female', 'n-6989d136122ef.jpg', '[\"ROLE_PATIENT\"]', '$2y$13$npDWIin8VEza4QnaE/E1hu0TLuXEFeNXWDFqKeAzeOplal4t9L7MW', 150, 100, 80, 'beginner', '[\"good\"]', '[\"gluten_free\"]', '[\"weight_loss\"]', NULL, '[]', NULL, NULL, NULL, NULL),
(12, 'test@gmail.com', 'test', 'sara', 'kkk', '22222222', '2026-02-01', 'female', 'n-698c4e2697d66.jpg', '[\"ROLE_PATIENT\"]', '$2y$13$/Ahao1vzTZW4JeQOdV.GK.7oHEgArRXorgS0yX9Qrh7Ql6L9E/JQu', 170, 50, 60, 'advanced', '[\"good\"]', '[\"halal\",\"keto\"]', '[\"muscle_gain\",\"endurance\"]', NULL, '[]', NULL, NULL, NULL, NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `blog_post`
--
ALTER TABLE `blog_post`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_BLOG_POST_SLUG` (`slug`),
  ADD KEY `IDX_BLOG_POST_AUTHOR` (`author_id`);

--
-- Index pour la table `content_interaction`
--
ALTER TABLE `content_interaction`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_INTERACTION_TARGET` (`target_type`,`target_id`),
  ADD KEY `IDX_INTERACTION_USER` (`user_id`);

--
-- Index pour la table `dm_conversation`
--
ALTER TABLE `dm_conversation`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `dm_message`
--
ALTER TABLE `dm_message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DM_MESSAGE_CONV` (`conversation_id`),
  ADD KEY `IDX_DM_MESSAGE_SENDER` (`sender_id`);

--
-- Index pour la table `dm_participant`
--
ALTER TABLE `dm_participant`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_DM_PARTICIPANT_CONV` (`conversation_id`),
  ADD KEY `IDX_DM_PARTICIPANT_USER` (`user_id`);

--
-- Index pour la table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

--
-- Index pour la table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id_event`);

--
-- Index pour la table `regime_alimentaire`
--
ALTER TABLE `regime_alimentaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_58CC75A6A76ED395` (`user_id`);

--
-- Index pour la table `repas`
--
ALTER TABLE `repas`
  ADD PRIMARY KEY (`id_repas`),
  ADD KEY `FK_REPAS_REGIME` (`regime_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_1483A5E9E7927C74` (`email`),
  ADD UNIQUE KEY `UNIQ_1483A5E9F85E0677` (`username`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `regime_alimentaire`
--
ALTER TABLE `regime_alimentaire`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `repas`
--
ALTER TABLE `repas`
  MODIFY `id_repas` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `regime_alimentaire`
--
ALTER TABLE `regime_alimentaire`
  ADD CONSTRAINT `FK_58CC75A6A76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `repas`
--
ALTER TABLE `repas`
  ADD CONSTRAINT `FK_REPAS_REGIME` FOREIGN KEY (`regime_id`) REFERENCES `regime_alimentaire` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
