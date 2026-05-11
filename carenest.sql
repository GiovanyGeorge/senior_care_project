-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 24, 2026 at 08:56 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `carenest`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_broadcasts`
--

CREATE TABLE `admin_broadcasts` (
  `broadcast_ID` int(10) NOT NULL,
  `admin_ID` int(10) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `message_body` varchar(500) DEFAULT NULL,
  `target_role` varchar(30) DEFAULT NULL,
  `severity_level` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashout_destinations`
--

CREATE TABLE `cashout_destinations` (
  `destination_ID` int(10) NOT NULL,
  `pal_ID` int(10) NOT NULL,
  `destination_type` varchar(30) DEFAULT NULL,
  `provider_name` varchar(100) DEFAULT NULL,
  `account_identifier` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashout_requests`
--

CREATE TABLE `cashout_requests` (
  `cashout_request_ID` int(10) NOT NULL,
  `destination_ID` int(10) NOT NULL,
  `pal_ID` int(10) NOT NULL,
  `points_requested` decimal(10,2) NOT NULL,
  `cash_equivalent` decimal(10,2) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_message`
--

CREATE TABLE `emergency_message` (
  `message_ID` int(10) NOT NULL,
  `sender_user_ID` int(10) NOT NULL,
  `emergency_ID` int(10) NOT NULL,
  `message_text` varchar(500) DEFAULT NULL,
  `location_snapshot` varchar(225) DEFAULT NULL,
  `medical_snapshot` varchar(500) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_threads`
--

CREATE TABLE `emergency_threads` (
  `thread_ID` int(10) NOT NULL,
  `visit_ID` int(10) DEFAULT NULL,
  `senior_ID` int(10) NOT NULL,
  `user_ID` int(10) NOT NULL,
  `status` varchar(30) DEFAULT 'Open',
  `priority_level` varchar(20) DEFAULT 'High',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `record_id` int(10) NOT NULL,
  `senior_ID` int(10) NOT NULL,
  `medical_notes` varchar(500) DEFAULT NULL,
  `mobility_notes` varchar(225) DEFAULT NULL,
  `allergies` varchar(255) DEFAULT NULL,
  `emergency_instructions` varchar(255) DEFAULT NULL,
  `must_acknowledge` tinyint(1) DEFAULT 0,
  `updated_by` int(10) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_ID` int(10) NOT NULL,
  `usersUser_ID` int(10) NOT NULL,
  `type` varchar(30) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `message_body` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `entity_ID` int(10) DEFAULT NULL,
  `entity_type` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pal_passed_requests`
--

CREATE TABLE `pal_passed_requests` (
  `visit_ID` int(10) NOT NULL,
  `pal_ID` int(10) NOT NULL,
  `action_type` varchar(20) DEFAULT NULL,
  `reason` varchar(225) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pal_profiles`
--

CREATE TABLE `pal_profiles` (
  `pal_ID` int(10) NOT NULL,
  `User_ID` int(10) NOT NULL,
  `skills` varchar(255) DEFAULT NULL,
  `rating_avg` decimal(3,2) DEFAULT NULL,
  `verification_status` varchar(30) DEFAULT 'Pending',
  `travel_radius_km` int(10) DEFAULT 5,
  `transport_mode` varchar(30) DEFAULT 'Walking'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proxy_senior_link`
--

CREATE TABLE `proxy_senior_link` (
  `proxyUser_ID` int(10) NOT NULL,
  `senior_ID` int(10) NOT NULL,
  `relationship_type` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `rating_ID` int(10) NOT NULL,
  `visit_ID` int(10) NOT NULL,
  `senior_ID` int(10) NOT NULL,
  `pal_ID` int(10) NOT NULL,
  `rating_score` decimal(3,2) NOT NULL,
  `comment` varchar(225) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `senior_profiles`
--

CREATE TABLE `senior_profiles` (
  `senior_ID` int(10) NOT NULL,
  `User_ID` int(10) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `comfort_profile` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(50) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `category_ID` int(10) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `base_points_cost` int(10) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `silverpoints_ledger`
--

CREATE TABLE `silverpoints_ledger` (
  `ledger_entry_ID` int(10) NOT NULL,
  `User_ID` int(10) NOT NULL,
  `visit_ID` int(10) DEFAULT NULL,
  `entry_type` varchar(30) NOT NULL,
  `points_amount` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skill_badges`
--

CREATE TABLE `skill_badges` (
  `badge_ID` int(10) NOT NULL,
  `pal_ID` int(10) NOT NULL,
  `badge_name` varchar(100) NOT NULL,
  `verification_status` varchar(30) DEFAULT 'Pending',
  `issued_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `certificate_url` varchar(225) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `User_ID` int(10) NOT NULL,
  `Fname` varchar(20) NOT NULL,
  `Lname` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_type` varchar(20) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_photo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Fname`, `Lname`, `email`, `password_hash`, `role_type`, `phone`, `created_at`, `profile_photo_url`, `is_active`) VALUES
(1, 'Test', 'User', 'testuser@example.com', '$2y$10$4aN5t6evWeOnNIX8akSPPOTXFCyGq4lPEnorNnEEn7bpl/grlod.a', 'senior', NULL, '2026-04-24 00:36:21', NULL, 1),
(7, 'Test', 'User', 'testuser2@example.com', '$2y$10$T/1k/Vc/pnTEk5Ixz1VLpeCgfSo/laxiZDMtB.Pv18lb9Tq1GZdAe', 'senior', NULL, '2026-04-24 00:55:33', NULL, 1),
(8, 'Nona', 'Emad', 'nona_test@example.com', '$2y$10$r0gvM0A86JRyqwHrXlBoQubWZcyej7mGXeF1j6dLrG/azu1zQNWj.', 'senior', NULL, '2026-04-24 01:07:52', NULL, 1),
(9, 'Ganna', 'Eamd', 'ganna@gmail.com', '$2y$10$c79eaHvQAu.2Lh..EeTme.D755EtCinO7nzPyFGL.XeN8eU./Cy1O', 'senior', NULL, '2026-04-24 01:37:17', NULL, 1),
(11, 'ganna', 'emad', 'ganna11@gmail.com', '$2y$10$8elUMNFfFQbirQf1LUWb9.ihcCpU0gsfkLW9YHYbY7Ttxzv9TQzvm', 'proxy', NULL, '2026-04-24 01:44:55', NULL, 1),
(12, 'sara', 'emad', 'sara15@gmail.com', '$2y$10$lHMCGKsBrvbIMSGhax7Miu9ih1tRPcoRgwwSH4GYCrfgNSM5oqJNq', 'senior', NULL, '2026-04-24 01:51:04', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `visit_requests`
--

CREATE TABLE `visit_requests` (
  `visit_ID` int(10) NOT NULL,
  `senior_ID` int(10) NOT NULL,
  `pal_ID` int(10) DEFAULT NULL,
  `proxy_ID` int(10) DEFAULT NULL,
  `category_ID` int(10) NOT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `request_type` varchar(20) DEFAULT NULL,
  `scheduled_start` datetime NOT NULL,
  `scheduled_end` datetime NOT NULL,
  `actual_checkin` datetime DEFAULT NULL,
  `actual_checkout` datetime DEFAULT NULL,
  `service_address` varchar(255) DEFAULT NULL,
  `task_details` varchar(300) DEFAULT NULL,
  `mood_observation` varchar(100) DEFAULT NULL,
  `points_reserved` decimal(10,2) DEFAULT 0.00,
  `points_paid` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_broadcasts`
--
ALTER TABLE `admin_broadcasts`
  ADD PRIMARY KEY (`broadcast_ID`),
  ADD KEY `admin_ID` (`admin_ID`);

--
-- Indexes for table `cashout_destinations`
--
ALTER TABLE `cashout_destinations`
  ADD PRIMARY KEY (`destination_ID`),
  ADD KEY `pal_ID` (`pal_ID`);

--
-- Indexes for table `cashout_requests`
--
ALTER TABLE `cashout_requests`
  ADD PRIMARY KEY (`cashout_request_ID`),
  ADD KEY `destination_ID` (`destination_ID`),
  ADD KEY `pal_ID` (`pal_ID`);

--
-- Indexes for table `emergency_message`
--
ALTER TABLE `emergency_message`
  ADD PRIMARY KEY (`message_ID`),
  ADD KEY `sender_user_ID` (`sender_user_ID`),
  ADD KEY `emergency_ID` (`emergency_ID`);

--
-- Indexes for table `emergency_threads`
--
ALTER TABLE `emergency_threads`
  ADD PRIMARY KEY (`thread_ID`),
  ADD KEY `visit_ID` (`visit_ID`),
  ADD KEY `senior_ID` (`senior_ID`),
  ADD KEY `user_ID` (`user_ID`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `senior_ID` (`senior_ID`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_ID`),
  ADD KEY `usersUser_ID` (`usersUser_ID`);

--
-- Indexes for table `pal_passed_requests`
--
ALTER TABLE `pal_passed_requests`
  ADD PRIMARY KEY (`visit_ID`,`pal_ID`),
  ADD KEY `pal_ID` (`pal_ID`);

--
-- Indexes for table `pal_profiles`
--
ALTER TABLE `pal_profiles`
  ADD PRIMARY KEY (`pal_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `proxy_senior_link`
--
ALTER TABLE `proxy_senior_link`
  ADD PRIMARY KEY (`proxyUser_ID`,`senior_ID`),
  ADD KEY `senior_ID` (`senior_ID`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`rating_ID`),
  ADD UNIQUE KEY `visit_ID` (`visit_ID`),
  ADD KEY `senior_ID` (`senior_ID`),
  ADD KEY `pal_ID` (`pal_ID`);

--
-- Indexes for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  ADD PRIMARY KEY (`senior_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`category_ID`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `silverpoints_ledger`
--
ALTER TABLE `silverpoints_ledger`
  ADD PRIMARY KEY (`ledger_entry_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `visit_ID` (`visit_ID`);

--
-- Indexes for table `skill_badges`
--
ALTER TABLE `skill_badges`
  ADD PRIMARY KEY (`badge_ID`),
  ADD KEY `pal_ID` (`pal_ID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `visit_requests`
--
ALTER TABLE `visit_requests`
  ADD PRIMARY KEY (`visit_ID`),
  ADD KEY `senior_ID` (`senior_ID`),
  ADD KEY `pal_ID` (`pal_ID`),
  ADD KEY `proxy_ID` (`proxy_ID`),
  ADD KEY `category_ID` (`category_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_broadcasts`
--
ALTER TABLE `admin_broadcasts`
  MODIFY `broadcast_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashout_destinations`
--
ALTER TABLE `cashout_destinations`
  MODIFY `destination_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashout_requests`
--
ALTER TABLE `cashout_requests`
  MODIFY `cashout_request_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_message`
--
ALTER TABLE `emergency_message`
  MODIFY `message_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_threads`
--
ALTER TABLE `emergency_threads`
  MODIFY `thread_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `record_id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pal_profiles`
--
ALTER TABLE `pal_profiles`
  MODIFY `pal_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `rating_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  MODIFY `senior_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `category_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `silverpoints_ledger`
--
ALTER TABLE `silverpoints_ledger`
  MODIFY `ledger_entry_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skill_badges`
--
ALTER TABLE `skill_badges`
  MODIFY `badge_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `User_ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `visit_requests`
--
ALTER TABLE `visit_requests`
  MODIFY `visit_ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_broadcasts`
--
ALTER TABLE `admin_broadcasts`
  ADD CONSTRAINT `admin_broadcasts_ibfk_1` FOREIGN KEY (`admin_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `cashout_destinations`
--
ALTER TABLE `cashout_destinations`
  ADD CONSTRAINT `cashout_destinations_ibfk_1` FOREIGN KEY (`pal_ID`) REFERENCES `pal_profiles` (`pal_ID`);

--
-- Constraints for table `cashout_requests`
--
ALTER TABLE `cashout_requests`
  ADD CONSTRAINT `cashout_requests_ibfk_1` FOREIGN KEY (`destination_ID`) REFERENCES `cashout_destinations` (`destination_ID`),
  ADD CONSTRAINT `cashout_requests_ibfk_2` FOREIGN KEY (`pal_ID`) REFERENCES `pal_profiles` (`pal_ID`);

--
-- Constraints for table `emergency_message`
--
ALTER TABLE `emergency_message`
  ADD CONSTRAINT `emergency_message_ibfk_1` FOREIGN KEY (`sender_user_ID`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `emergency_message_ibfk_2` FOREIGN KEY (`emergency_ID`) REFERENCES `emergency_threads` (`thread_ID`);

--
-- Constraints for table `emergency_threads`
--
ALTER TABLE `emergency_threads`
  ADD CONSTRAINT `emergency_threads_ibfk_1` FOREIGN KEY (`visit_ID`) REFERENCES `visit_requests` (`visit_ID`),
  ADD CONSTRAINT `emergency_threads_ibfk_2` FOREIGN KEY (`senior_ID`) REFERENCES `senior_profiles` (`senior_ID`),
  ADD CONSTRAINT `emergency_threads_ibfk_3` FOREIGN KEY (`user_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`senior_ID`) REFERENCES `senior_profiles` (`senior_ID`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`usersUser_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `pal_passed_requests`
--
ALTER TABLE `pal_passed_requests`
  ADD CONSTRAINT `pal_passed_requests_ibfk_1` FOREIGN KEY (`visit_ID`) REFERENCES `visit_requests` (`visit_ID`),
  ADD CONSTRAINT `pal_passed_requests_ibfk_2` FOREIGN KEY (`pal_ID`) REFERENCES `pal_profiles` (`pal_ID`);

--
-- Constraints for table `pal_profiles`
--
ALTER TABLE `pal_profiles`
  ADD CONSTRAINT `pal_profiles_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `proxy_senior_link`
--
ALTER TABLE `proxy_senior_link`
  ADD CONSTRAINT `proxy_senior_link_ibfk_1` FOREIGN KEY (`senior_ID`) REFERENCES `senior_profiles` (`senior_ID`),
  ADD CONSTRAINT `proxy_senior_link_ibfk_2` FOREIGN KEY (`proxyUser_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`visit_ID`) REFERENCES `visit_requests` (`visit_ID`),
  ADD CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`senior_ID`) REFERENCES `senior_profiles` (`senior_ID`),
  ADD CONSTRAINT `ratings_ibfk_3` FOREIGN KEY (`pal_ID`) REFERENCES `pal_profiles` (`pal_ID`);

--
-- Constraints for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  ADD CONSTRAINT `senior_profiles_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `silverpoints_ledger`
--
ALTER TABLE `silverpoints_ledger`
  ADD CONSTRAINT `silverpoints_ledger_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `silverpoints_ledger_ibfk_2` FOREIGN KEY (`visit_ID`) REFERENCES `visit_requests` (`visit_ID`);

--
-- Constraints for table `skill_badges`
--
ALTER TABLE `skill_badges`
  ADD CONSTRAINT `skill_badges_ibfk_1` FOREIGN KEY (`pal_ID`) REFERENCES `pal_profiles` (`pal_ID`);

--
-- Constraints for table `visit_requests`
--
ALTER TABLE `visit_requests`
  ADD CONSTRAINT `visit_requests_ibfk_1` FOREIGN KEY (`senior_ID`) REFERENCES `senior_profiles` (`senior_ID`),
  ADD CONSTRAINT `visit_requests_ibfk_2` FOREIGN KEY (`pal_ID`) REFERENCES `pal_profiles` (`pal_ID`),
  ADD CONSTRAINT `visit_requests_ibfk_3` FOREIGN KEY (`proxy_ID`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `visit_requests_ibfk_4` FOREIGN KEY (`category_ID`) REFERENCES `service_categories` (`category_ID`);

-- --------------------------------------------------------
-- App schema extensions (formerly separate sql/*.sql files)
-- Safe to re-run on MariaDB 10.4+ (IF NOT EXISTS / IF NOT EXISTS indexes)
-- --------------------------------------------------------

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `age` int(10) DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `national_id` varchar(30) DEFAULT NULL AFTER `age`;

ALTER TABLE `users`
  ADD UNIQUE KEY IF NOT EXISTS `uq_users_national_id` (`national_id`);

ALTER TABLE `pal_profiles`
  ADD COLUMN IF NOT EXISTS `max_daily_hours` int(10) NOT NULL DEFAULT 8 AFTER `transport_mode`;

ALTER TABLE `service_categories`
  ADD COLUMN IF NOT EXISTS `max_duration_hours` int(10) NOT NULL DEFAULT 4 AFTER `base_points_cost`;

CREATE TABLE IF NOT EXISTS `proxy_profiles` (
  `proxy_profile_ID` int(10) NOT NULL AUTO_INCREMENT,
  `User_ID` int(10) NOT NULL,
  `relationship_notes` varchar(255) DEFAULT NULL,
  `preferred_contact_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`proxy_profile_ID`),
  UNIQUE KEY `uq_proxy_profiles_user` (`User_ID`),
  CONSTRAINT `fk_proxy_profiles_user` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `background_checks` (
  `check_ID` int(10) NOT NULL AUTO_INCREMENT,
  `pal_ID` int(10) NOT NULL,
  `badge_ID` int(10) DEFAULT NULL,
  `check_type` varchar(50) NOT NULL DEFAULT 'SkillBadge',
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `reviewer_user_ID` int(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`check_ID`),
  KEY `idx_background_checks_pal` (`pal_ID`),
  KEY `idx_background_checks_status` (`status`),
  CONSTRAINT `fk_background_checks_pal` FOREIGN KEY (`pal_ID`) REFERENCES `pal_profiles` (`pal_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_background_checks_badge` FOREIGN KEY (`badge_ID`) REFERENCES `skill_badges` (`badge_ID`) ON DELETE SET NULL,
  CONSTRAINT `fk_background_checks_reviewer` FOREIGN KEY (`reviewer_user_ID`) REFERENCES `users` (`User_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `escrow_holds` (
  `escrow_ID` int(10) NOT NULL AUTO_INCREMENT,
  `visit_ID` int(10) NOT NULL,
  `user_ID` int(10) NOT NULL,
  `points_amount` decimal(10,2) NOT NULL,
  `hold_status` varchar(30) NOT NULL DEFAULT 'Held',
  `released_at` datetime DEFAULT NULL,
  `release_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`escrow_ID`),
  KEY `idx_escrow_visit` (`visit_ID`),
  KEY `idx_escrow_user` (`user_ID`),
  CONSTRAINT `fk_escrow_visit` FOREIGN KEY (`visit_ID`) REFERENCES `visit_requests` (`visit_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_escrow_user` FOREIGN KEY (`user_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `visit_reports` (
  `report_ID` int(10) NOT NULL AUTO_INCREMENT,
  `visit_ID` int(10) NOT NULL,
  `pal_user_ID` int(10) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `report_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`report_ID`),
  KEY `visit_ID` (`visit_ID`),
  KEY `pal_user_ID` (`pal_user_ID`),
  CONSTRAINT `visit_reports_ibfk_1` FOREIGN KEY (`visit_ID`) REFERENCES `visit_requests` (`visit_ID`) ON DELETE CASCADE,
  CONSTRAINT `visit_reports_ibfk_2` FOREIGN KEY (`pal_user_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
