-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 26, 2025 at 07:29 AM
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
-- Database: `treehero`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `achievement_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_name` varchar(50) DEFAULT NULL,
  `color_class` varchar(50) DEFAULT NULL,
  `requirement_type` varchar(20) NOT NULL,
  `requirement_value` int(11) NOT NULL,
  `xp_reward` int(11) NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `achievements`
--

INSERT INTO `achievements` (`achievement_id`, `name`, `description`, `icon_name`, `color_class`, `requirement_type`, `requirement_value`, `xp_reward`) VALUES
(1, 'First Leaf', 'Plant your very first tree!', 'leaf', 'green', 'total_trees', 1, 50),
(2, 'Sapling Sprout', 'Plant 5 trees!', 'tree', 'emerald', 'total_trees', 5, 100),
(3, 'Forest Creator', 'Plant 10 trees!', 'forest', 'green', 'total_trees', 10, 250),
(4, 'Sapling Saver', 'Plant 25 trees!', 'sprout', 'lime', 'total_trees', 25, 350),
(5, 'Arbor Achiever', 'Plant 50 trees!', 'tree-deciduous', 'sky', 'total_trees', 50, 450),
(6, 'Dendro Daredevil', 'Plant 100 trees!', 'tree-pine', 'teal', 'total_trees', 100, 600),
(7, 'Species Explorer', 'Plant 3 different tree species!', 'globe', 'blue', 'distinct_species', 3, 150),
(8, 'Species Master', 'Plant 5 different tree species!', 'crown', 'yellow', 'distinct_species', 5, 300),
(9, 'Ecological Explorer', 'Plant 7 different tree species!', 'map', 'purple', 'distinct_species', 7, 400),
(10, 'Conservationist', 'Plant 10 different tree species!', 'shield-check', 'orange', 'distinct_species', 10, 550),
(11, 'Weekly Warrior', 'Maintain a 7-day streak', 'calendar', 'purple', 'streak_days', 7, 200),
(12, 'Monthly Master', 'Maintain a 30-day streak', 'calendar-check', 'blue', 'streak_days', 30, 1000);

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_date` date NOT NULL,
  `trees_planted` int(11) NOT NULL DEFAULT 1,
  `tree_species` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`activity_id`, `user_id`, `activity_date`, `trees_planted`, `tree_species`, `description`, `created_at`) VALUES
(1, 1, '2025-06-05', 1, NULL, 'oak tree', '2025-06-24 06:39:27'),
(2, 1, '2025-06-24', 4, 'Tualang', '', '2025-06-24 06:51:45'),
(3, 1, '2025-06-24', 1, 'Tualang', '', '2025-06-24 07:16:44'),
(4, 1, '2025-06-24', 1, 'Chengal', '', '2025-06-24 07:18:10'),
(5, 1, '2025-06-24', 1, 'Keruing', '', '2025-06-24 07:18:15'),
(6, 1, '2025-06-25', 1, 'Chengal', '', '2025-06-24 07:21:20'),
(7, 1, '2025-06-25', 1, 'Keruing', '', '2025-06-24 07:22:53'),
(8, 1, '2025-06-24', 5, 'Balau', '', '2025-06-24 07:24:03'),
(9, 1, '2025-06-24', 1, 'Keruing', '', '2025-06-24 08:47:08'),
(10, 1, '2025-06-24', 50, 'Balau', '', '2025-06-24 09:22:52'),
(11, 1, '2025-06-24', 50, 'Balau', '', '2025-06-24 09:24:12'),
(12, 1, '2025-06-24', 50, 'Chengal', '', '2025-06-24 09:35:00'),
(13, 1, '2025-06-18', 50, 'Rubber', '', '2025-06-24 09:37:44'),
(14, 1, '2025-06-25', 50, 'Chengal', '', '2025-06-24 09:39:43'),
(15, 1, '2025-06-11', 50, 'Rubber', '', '2025-06-24 09:44:11'),
(16, 1, '2025-06-17', 4, 'Meranti', '', '2025-06-24 10:17:33'),
(17, 1, '2025-06-24', 1, 'Tualang', '', '2025-06-24 11:04:30'),
(18, 1, '2025-06-25', 100, 'Meranti', '', '2025-06-24 11:04:46'),
(19, 1, '2025-06-24', 1, 'Mango', '', '2025-06-24 11:35:18'),
(20, 4, '2025-06-26', 1, 'Tualang', '', '2025-06-24 11:38:36'),
(21, 5, '2025-06-25', 10, 'Meranti', '', '2025-06-24 12:12:58'),
(22, 6, '2025-06-24', 10, 'Chengal', '', '2025-06-24 14:59:17'),
(23, 1, '2025-06-26', 1, 'Meranti', '', '2025-06-25 03:01:17'),
(24, 1, '2025-06-27', 1, 'Tualang', '', '2025-06-25 03:02:51'),
(25, 1, '2025-06-28', 1, 'Tualang', '', '2025-06-25 03:02:59'),
(26, 7, '2025-06-25', 1, 'Tualang', '', '2025-06-25 05:09:42'),
(27, 7, '2025-06-26', 100, 'Meranti', '', '2025-06-25 05:11:22');

-- --------------------------------------------------------

--
-- Table structure for table `activity_comments`
--

CREATE TABLE `activity_comments` (
  `comment_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_comments`
--

INSERT INTO `activity_comments` (`comment_id`, `activity_id`, `user_id`, `comment_text`, `created_at`) VALUES
(1, 18, 1, 'niceee', '2025-06-24 11:27:00'),
(2, 19, 4, 'Really good!!!', '2025-06-24 11:38:53'),
(3, 21, 5, 'Great!!', '2025-06-24 14:19:32'),
(4, 21, 6, 'Really good!!!', '2025-06-24 14:59:51'),
(5, 22, 7, 'NIce!!', '2025-06-25 05:10:42'),
(6, 26, 7, 'niceee', '2025-06-25 05:10:56');

-- --------------------------------------------------------

--
-- Table structure for table `activity_likes`
--

CREATE TABLE `activity_likes` (
  `like_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_likes`
--

INSERT INTO `activity_likes` (`like_id`, `activity_id`, `user_id`, `created_at`) VALUES
(1, 18, 1, '2025-06-24 11:08:11'),
(2, 17, 1, '2025-06-24 11:08:16'),
(3, 19, 4, '2025-06-24 11:38:44'),
(5, 7, 1, '2025-06-24 11:49:25'),
(7, 6, 1, '2025-06-24 11:49:27'),
(8, 20, 5, '2025-06-24 12:13:14'),
(9, 21, 5, '2025-06-24 14:19:26'),
(11, 21, 6, '2025-06-24 14:59:46'),
(12, 22, 6, '2025-06-24 15:00:24'),
(14, 22, 7, '2025-06-25 05:10:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `xp` int(11) NOT NULL DEFAULT 0,
  `last_activity_date` date DEFAULT NULL,
  `current_streak_days` int(11) NOT NULL DEFAULT 0,
  `profile_picture_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `created_at`, `xp`, `last_activity_date`, `current_streak_days`, `profile_picture_url`) VALUES
(1, 'Danny', 'dainijfr@gmail.com', '$2y$10$cfYfw/4jUusyay/7Hx.vsuuDvygsVbvtmFcLD.BhkEbMrycQMYgPy', '2025-06-24 06:36:04', 6750, '2025-06-28', 4, 'uploads/profile_1_1750756428.jpg'),
(2, 'Leong Wei', 'LW@gmail.com', '$2y$10$dWRUULeB62bK6KpnPz.NWeeeRwfoHeTKe.5bq8ABd.EucgX5Iact.', '2025-06-24 07:50:15', 0, NULL, 0, NULL),
(4, 'HackDemo1', 'HackDemo1@gmail.com', '$2y$10$PI8IeV/USnsnvFC6Fn6odOtiP.xcvLShjFpix.f/TqWUVi6YZr06O', '2025-06-24 11:37:49', 60, '2025-06-26', 1, 'uploads/profile_4_1750765098.jpg'),
(5, 'HackDemo2', 'HackDemo2@gmal.com', '$2y$10$XNExE8pAQOA3JtzvUqFG0e46YeCF/yF2PCqz5Xhz.r.Xu3MBkhTDO', '2025-06-24 12:12:24', 500, '2025-06-25', 1, 'uploads/profile_5_1750767159.jpg'),
(6, 'HackDemo3', 'HackDemo3@gmail.com', '$2y$10$x/APOaU9NAfZQK7E2.j0ZOoVc7eaRvkycqsZGLHlKWbtOKZ6F9quu', '2025-06-24 14:58:29', 500, '2025-06-24', 1, 'uploads/profile_6_1750777127.jpg'),
(7, 'HackDemo4', 'HackDemo4@gmal.com', '$2y$10$bDa3OEUiw1JMqQ618iPoF.p/1WUPvZuKV4AVNMocX/f23zG9isU5y', '2025-06-25 05:09:01', 2810, '2025-06-26', 2, 'uploads/profile_7_1750828163.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `user_achievements`
--

CREATE TABLE `user_achievements` (
  `user_achievement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_achievements`
--

INSERT INTO `user_achievements` (`user_achievement_id`, `user_id`, `achievement_id`, `earned_at`) VALUES
(1, 1, 1, '2025-06-24 10:17:19'),
(2, 1, 2, '2025-06-24 10:17:19'),
(3, 1, 7, '2025-06-24 10:17:19'),
(4, 1, 3, '2025-06-24 10:17:19'),
(5, 1, 8, '2025-06-24 10:17:19'),
(6, 1, 4, '2025-06-24 10:17:19'),
(7, 1, 5, '2025-06-24 10:17:19'),
(8, 1, 6, '2025-06-24 10:17:19'),
(9, 1, 9, '2025-06-24 11:35:18'),
(10, 4, 1, '2025-06-24 11:38:36'),
(11, 5, 1, '2025-06-24 12:12:58'),
(12, 5, 2, '2025-06-24 12:12:58'),
(13, 5, 3, '2025-06-24 12:12:58'),
(14, 6, 1, '2025-06-24 14:59:17'),
(15, 6, 2, '2025-06-24 14:59:17'),
(16, 6, 3, '2025-06-24 14:59:17'),
(17, 7, 1, '2025-06-25 05:09:42'),
(18, 7, 2, '2025-06-25 05:11:22'),
(19, 7, 3, '2025-06-25 05:11:22'),
(20, 7, 4, '2025-06-25 05:11:22'),
(21, 7, 5, '2025-06-25 05:11:22'),
(22, 7, 6, '2025-06-25 05:11:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`achievement_id`);

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `activity_comments`
--
ALTER TABLE `activity_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `activity_id` (`activity_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `activity_likes`
--
ALTER TABLE `activity_likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `activity_id` (`activity_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD PRIMARY KEY (`user_achievement_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`achievement_id`),
  ADD KEY `achievement_id` (`achievement_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `achievement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `activity_comments`
--
ALTER TABLE `activity_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `activity_likes`
--
ALTER TABLE `activity_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_achievements`
--
ALTER TABLE `user_achievements`
  MODIFY `user_achievement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_comments`
--
ALTER TABLE `activity_comments`
  ADD CONSTRAINT `activity_comments_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`activity_id`),
  ADD CONSTRAINT `activity_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `activity_likes`
--
ALTER TABLE `activity_likes`
  ADD CONSTRAINT `activity_likes_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`activity_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`achievement_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
