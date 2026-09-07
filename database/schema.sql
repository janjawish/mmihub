-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Schéma anonymisé pour un environnement de développement.
-- Ne contient aucune donnée utilisateur ni aucun secret.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `mmihub`
--

-- --------------------------------------------------------

--
-- Structure de la table `absences`
--

CREATE TABLE `absences` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `semester_id` tinyint(3) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `absence_date` date NOT NULL,
  `hours` decimal(4,2) NOT NULL DEFAULT 1.00,
  `justified` tinyint(1) NOT NULL DEFAULT 0,
  `comment` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `absences`
--

-- --------------------------------------------------------

--
-- Structure de la table `chat_admin_notifications`
--

CREATE TABLE `chat_admin_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `target_user_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `chat_admin_notifications`
--

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `content_filtered` text NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `chat_messages`
--

-- --------------------------------------------------------

--
-- Structure de la table `chat_message_reactions`
--

CREATE TABLE `chat_message_reactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `message_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `emoji` varchar(16) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `modules`
--

CREATE TABLE `modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('ressource','sae') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `modules`
--

-- --------------------------------------------------------

--
-- Structure de la table `module_assessments`
--

CREATE TABLE `module_assessments` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `semester_id` tinyint(3) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `kind` enum('ds','tp','unique') NOT NULL,
  `grade` decimal(4,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `module_assessments`
--

-- --------------------------------------------------------

--
-- Structure de la table `module_grades`
--

CREATE TABLE `module_grades` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `semester_id` tinyint(3) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `note_ds` decimal(4,2) DEFAULT NULL,
  `note_tp` decimal(4,2) DEFAULT NULL,
  `final_grade` decimal(4,2) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `module_grades`
--

-- --------------------------------------------------------

--
-- Structure de la table `profiles`
--

CREATE TABLE `profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `public_slug` varchar(64) DEFAULT NULL,
  `headline` varchar(255) DEFAULT NULL,
  `about` text DEFAULT NULL,
  `skills` varchar(255) DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `show_year` tinyint(1) NOT NULL DEFAULT 1,
  `show_parcours` tinyint(1) NOT NULL DEFAULT 1,
  `show_photo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `profiles`
--

-- --------------------------------------------------------

--
-- Structure de la table `semesters`
--

CREATE TABLE `semesters` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `label` varchar(100) NOT NULL,
  `but_year` tinyint(3) UNSIGNED NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `semesters`
--

-- --------------------------------------------------------

--
-- Structure de la table `service_offers`
--

CREATE TABLE `service_offers` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `base_price_cents` int(10) UNSIGNED DEFAULT NULL,
  `is_price_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','online','rejected') NOT NULL DEFAULT 'pending',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `views_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `contact_clicks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `public_slug` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `service_offers`
--

-- --------------------------------------------------------

--
-- Structure de la table `service_offer_contacts`
--

CREATE TABLE `service_offer_contacts` (
  `offer_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `service_offer_contacts`
--

-- --------------------------------------------------------

--
-- Structure de la table `service_offer_images`
--

CREATE TABLE `service_offer_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `offer_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `service_offer_views`
--

CREATE TABLE `service_offer_views` (
  `offer_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `service_offer_views`
--

-- --------------------------------------------------------

--
-- Structure de la table `service_profiles`
--

CREATE TABLE `service_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `profile_type` enum('person','company') NOT NULL,
  `company_status` enum('none','pending','approved') NOT NULL DEFAULT 'none',
  `company_name` varchar(255) DEFAULT NULL,
  `siren` varchar(20) DEFAULT NULL,
  `siret` varchar(20) DEFAULT NULL,
  `headline` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `portfolio_url` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `public_email` varchar(255) DEFAULT NULL,
  `sectors` varchar(255) DEFAULT NULL,
  `zones` varchar(255) DEFAULT NULL,
  `is_remote_only` tinyint(1) NOT NULL DEFAULT 0,
  `role_webdev` tinyint(1) NOT NULL DEFAULT 0,
  `role_crea` tinyint(1) NOT NULL DEFAULT 0,
  `role_photo` tinyint(1) NOT NULL DEFAULT 0,
  `role_av` tinyint(1) NOT NULL DEFAULT 0,
  `is_generalist` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `service_profiles`
--

-- --------------------------------------------------------

--
-- Structure de la table `supp_users`
--

CREATE TABLE `supp_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `original_user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `but_year` tinyint(3) UNSIGNED DEFAULT NULL,
  `parcours` enum('none','crea','dev') DEFAULT 'none',
  `formation_type` enum('none','fi','fa') DEFAULT 'none',
  `deleted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `delete_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `supp_users`
--

-- --------------------------------------------------------

--
-- Structure de la table `temp_users`
--

CREATE TABLE `temp_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `birth_date` date NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `but_year` tinyint(3) UNSIGNED NOT NULL,
  `parcours` enum('none','crea','dev') DEFAULT 'none',
  `formation_type` enum('none','fi','fa') DEFAULT 'none',
  `verification_token` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ues`
--

CREATE TABLE `ues` (
  `id` int(10) UNSIGNED NOT NULL,
  `semester_id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `competence` enum('C1','C2','C3','C4','C5') NOT NULL,
  `ects` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ues`
--

-- --------------------------------------------------------

--
-- Structure de la table `ue_modules`
--

CREATE TABLE `ue_modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `ue_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `coef` decimal(5,2) NOT NULL,
  `parcours` enum('none','crea','dev') NOT NULL DEFAULT 'none',
  `formation_type` enum('none','fi','fa') NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ue_modules`
--

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `birth_date` date NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `but_year` tinyint(3) UNSIGNED NOT NULL,
  `parcours` enum('none','crea','dev') DEFAULT 'none',
  `formation_type` enum('none','fi','fa') DEFAULT 'none',
  `current_semester_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `current_semester_avg` decimal(4,2) DEFAULT NULL,
  `overall_avg` decimal(4,2) DEFAULT NULL,
  `chat_role` enum('user','admin') NOT NULL DEFAULT 'user',
  `chat_muted_until` datetime DEFAULT NULL,
  `chat_banned_until` datetime DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_login_ua` varchar(255) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `twofa_method` enum('none','email') NOT NULL DEFAULT 'none',
  `twofa_temp_code` varchar(10) DEFAULT NULL,
  `twofa_temp_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

-- --------------------------------------------------------

--
-- Structure de la table `user_semester_stats`
--

CREATE TABLE `user_semester_stats` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `semester_id` tinyint(3) UNSIGNED NOT NULL,
  `avg_global` decimal(4,2) DEFAULT NULL,
  `avg_c1` decimal(4,2) DEFAULT NULL,
  `avg_c2` decimal(4,2) DEFAULT NULL,
  `avg_c3` decimal(4,2) DEFAULT NULL,
  `avg_c4` decimal(4,2) DEFAULT NULL,
  `avg_c5` decimal(4,2) DEFAULT NULL,
  `status_c1` enum('ok','warn','fail') NOT NULL DEFAULT 'warn',
  `status_c2` enum('ok','warn','fail') NOT NULL DEFAULT 'warn',
  `status_c3` enum('ok','warn','fail') NOT NULL DEFAULT 'warn',
  `status_c4` enum('ok','warn','fail') NOT NULL DEFAULT 'warn',
  `status_c5` enum('ok','warn','fail') NOT NULL DEFAULT 'warn',
  `competences_validated` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `has_eliminatory` tinyint(1) NOT NULL DEFAULT 0,
  `semester_valid` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_semester_stats`
--

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `absences`
--
ALTER TABLE `absences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_abs_user` (`user_id`),
  ADD KEY `fk_abs_semester` (`semester_id`),
  ADD KEY `fk_abs_module` (`module_id`);

--
-- Index pour la table `chat_admin_notifications`
--
ALTER TABLE `chat_admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_notif_target` (`target_user_id`,`is_read`),
  ADD KEY `fk_chat_notif_admin` (`admin_id`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_created` (`created_at`),
  ADD KEY `idx_chat_user` (`user_id`),
  ADD KEY `idx_chat_parent` (`parent_id`);

--
-- Index pour la table `chat_message_reactions`
--
ALTER TABLE `chat_message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_chat_reaction` (`message_id`,`user_id`,`emoji`),
  ADD KEY `idx_chat_reactions_msg` (`message_id`),
  ADD KEY `idx_chat_reactions_user` (`user_id`);

--
-- Index pour la table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_modules_code` (`code`);

--
-- Index pour la table `module_assessments`
--
ALTER TABLE `module_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ma_user_semester` (`user_id`,`semester_id`),
  ADD KEY `idx_ma_user_semester_module` (`user_id`,`semester_id`,`module_id`),
  ADD KEY `fk_ma_semester` (`semester_id`),
  ADD KEY `fk_ma_module` (`module_id`);

--
-- Index pour la table `module_grades`
--
ALTER TABLE `module_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_semester_module` (`user_id`,`semester_id`,`module_id`),
  ADD KEY `idx_module_grades_semester` (`semester_id`),
  ADD KEY `fk_module_grades_module` (`module_id`);

--
-- Index pour la table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_profiles_slug` (`public_slug`),
  ADD KEY `fk_profiles_user` (`user_id`);

--
-- Index pour la table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `service_offers`
--
ALTER TABLE `service_offers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_service_offers_public_slug` (`public_slug`),
  ADD KEY `fk_so_profile` (`profile_id`),
  ADD KEY `idx_so_status` (`status`),
  ADD KEY `idx_so_views` (`views_count`),
  ADD KEY `idx_so_created` (`created_at`);

--
-- Index pour la table `service_offer_contacts`
--
ALTER TABLE `service_offer_contacts`
  ADD PRIMARY KEY (`offer_id`,`user_id`);

--
-- Index pour la table `service_offer_images`
--
ALTER TABLE `service_offer_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_soi_offer_pos` (`offer_id`,`position`);

--
-- Index pour la table `service_offer_views`
--
ALTER TABLE `service_offer_views`
  ADD PRIMARY KEY (`offer_id`,`user_id`);

--
-- Index pour la table `service_profiles`
--
ALTER TABLE `service_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Index pour la table `supp_users`
--
ALTER TABLE `supp_users`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `temp_users`
--
ALTER TABLE `temp_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `ues`
--
ALTER TABLE `ues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ues_semester` (`semester_id`);

--
-- Index pour la table `ue_modules`
--
ALTER TABLE `ue_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ue_modules_filter` (`ue_id`,`parcours`,`formation_type`),
  ADD KEY `fk_ue_modules_module` (`module_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `user_semester_stats`
--
ALTER TABLE `user_semester_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_semester` (`user_id`,`semester_id`),
  ADD KEY `idx_user_semester_stats_user` (`user_id`),
  ADD KEY `fk_uss_semester` (`semester_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `absences`
--
ALTER TABLE `absences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `chat_admin_notifications`
--
ALTER TABLE `chat_admin_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT pour la table `chat_message_reactions`
--
ALTER TABLE `chat_message_reactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT pour la table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=202;

--
-- AUTO_INCREMENT pour la table `module_assessments`
--
ALTER TABLE `module_assessments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT pour la table `module_grades`
--
ALTER TABLE `module_grades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1314;

--
-- AUTO_INCREMENT pour la table `profiles`
--
ALTER TABLE `profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `service_offers`
--
ALTER TABLE `service_offers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `service_offer_images`
--
ALTER TABLE `service_offer_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `service_profiles`
--
ALTER TABLE `service_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `supp_users`
--
ALTER TABLE `supp_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `temp_users`
--
ALTER TABLE `temp_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `ues`
--
ALTER TABLE `ues`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT pour la table `ue_modules`
--
ALTER TABLE `ue_modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=360;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `user_semester_stats`
--
ALTER TABLE `user_semester_stats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=265;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `absences`
--
ALTER TABLE `absences`
  ADD CONSTRAINT `fk_abs_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`),
  ADD CONSTRAINT `fk_abs_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`),
  ADD CONSTRAINT `fk_abs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `chat_admin_notifications`
--
ALTER TABLE `chat_admin_notifications`
  ADD CONSTRAINT `fk_chat_notif_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_notif_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_parent` FOREIGN KEY (`parent_id`) REFERENCES `chat_messages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `chat_message_reactions`
--
ALTER TABLE `chat_message_reactions`
  ADD CONSTRAINT `fk_chat_react_msg` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_react_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `module_assessments`
--
ALTER TABLE `module_assessments`
  ADD CONSTRAINT `fk_ma_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ma_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ma_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `module_grades`
--
ALTER TABLE `module_grades`
  ADD CONSTRAINT `fk_module_grades_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_module_grades_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_module_grades_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `profiles`
--
ALTER TABLE `profiles`
  ADD CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `service_offers`
--
ALTER TABLE `service_offers`
  ADD CONSTRAINT `fk_so_profile` FOREIGN KEY (`profile_id`) REFERENCES `service_profiles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `service_offer_images`
--
ALTER TABLE `service_offer_images`
  ADD CONSTRAINT `fk_soi_offer` FOREIGN KEY (`offer_id`) REFERENCES `service_offers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `service_profiles`
--
ALTER TABLE `service_profiles`
  ADD CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `ues`
--
ALTER TABLE `ues`
  ADD CONSTRAINT `fk_ues_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `ue_modules`
--
ALTER TABLE `ue_modules`
  ADD CONSTRAINT `fk_ue_modules_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ue_modules_ue` FOREIGN KEY (`ue_id`) REFERENCES `ues` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_semester_stats`
--
ALTER TABLE `user_semester_stats`
  ADD CONSTRAINT `fk_uss_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_uss_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
