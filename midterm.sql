-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2025 at 05:49 PM
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
-- Database: `midterm`
--

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_tokens`
--

CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `labels`
--

CREATE TABLE `labels` (
  `label_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `labels`
--

INSERT INTO `labels` (`label_id`, `user_id`, `name`) VALUES
(12, 10, 'test'),
(14, 10, '12'),
(15, 10, '14');

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `note_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pinned_at` datetime DEFAULT NULL,
  `images` text DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `note_labels`
--

CREATE TABLE `note_labels` (
  `note_id` int(11) NOT NULL,
  `label_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shared_notes`
--

CREATE TABLE `shared_notes` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `permission` enum('read','edit') NOT NULL DEFAULT 'read',
  `verification_token` varchar(64) NOT NULL,
  `expires` datetime NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `totp_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `avatar` varchar(255) DEFAULT NULL,
  `preferences` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `created_at`, `updated_at`, `is_verified`, `totp_secret`, `two_factor_enabled`, `avatar`, `preferences`) VALUES
(10, 'kiet', 'kietvdki@gmail.com', '$2y$10$JlB8XJOX0lnRpr2XIl0dLefe14zHPb2i.VZPEJtfLZUjDK0441xfK', '2025-05-12 14:40:03', '2025-05-12 22:38:01', 1, NULL, 0, 'uploads/avatars/avatar_6821a65faecc0.png', '{\"theme\":\"light\",\"note_layout\":\"list\",\"font_size\":\"medium\",\"note_color\":\"#ffffff\"}'),
(11, 'kiet2', 'kietvdbd@gmail.com', '$2y$10$6bkL9pyDqr3TU2y6Cq.xJ.IC364mQMbzr7IGtS.cM7lTde2UbMSKS', '2025-05-12 21:24:44', '2025-05-12 21:25:06', 1, NULL, 0, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `labels`
--
ALTER TABLE `labels`
  ADD PRIMARY KEY (`label_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `note_labels`
--
ALTER TABLE `note_labels`
  ADD PRIMARY KEY (`note_id`,`label_id`),
  ADD KEY `label_id` (`label_id`);

--
-- Indexes for table `shared_notes`
--
ALTER TABLE `shared_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_id` (`note_id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `labels`
--
ALTER TABLE `labels`
  MODIFY `label_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `shared_notes`
--
ALTER TABLE `shared_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD CONSTRAINT `email_verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `labels`
--
ALTER TABLE `labels`
  ADD CONSTRAINT `labels_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `note_labels`
--
ALTER TABLE `note_labels`
  ADD CONSTRAINT `note_labels_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`note_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `note_labels_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `labels` (`label_id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_notes`
--
ALTER TABLE `shared_notes`
  ADD CONSTRAINT `shared_notes_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`note_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shared_notes_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shared_notes_ibfk_3` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
