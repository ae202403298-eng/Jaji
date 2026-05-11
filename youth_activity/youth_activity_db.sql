-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 11, 2026 at 10:56 AM
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
-- Database: `youth_activity_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `status` enum('present','absent') DEFAULT 'absent',
  `attendance_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `event_id`, `status`, `attendance_date`) VALUES
(4, 14, 4, 'present', '2026-05-06'),
(5, 15, 8, 'present', '2026-05-11');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `event_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `event_end_date` datetime DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT 0.00,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_name`, `description`, `event_date`, `event_end_date`, `location`, `fee`, `is_free`, `created_at`) VALUES
(4, 'Sunday Church', 'Sunday Church is a weekly gathering where people come together to worship, pray, and strengthen their faith. It is a time for listening to God’s word, singing praises, and building fellowship with others in the community. Sunday Church helps individuals grow spiritually, find peace and guidance, and deepen their relationship with God while sharing love, hope, and encouragement with one another.', '2026-05-06 08:30:00', '2026-05-06 12:30:00', 'Divisoria Church', 0.00, 1, '2026-05-03 08:27:48'),
(7, 'YOUTH CAMP 2026', 'Join our Youth Camp for a fun and meaningful experience filled with teamwork, friendship, learning, and exciting activities that inspire leadership, confidence, and personal growth.', '2026-05-13 07:15:00', '2026-05-15 10:15:00', 'Azzura Beach Resort', 300.00, 0, '2026-05-08 11:14:47'),
(8, 'Outreach Program', 'An Outreach Program is a community-based activity where individuals or organizations provide help, services, or support to people in need. It may include giving food, offering medical assistance, conducting educational activities, or supporting local communities.\r\n\r\nThe main goal of an outreach program is to promote compassion, social responsibility, and positive impact by reaching out and helping others.', '2026-05-11 10:00:00', '2026-05-11 17:00:00', 'Sinunuc Brgy Hall', 500.00, 0, '2026-05-11 07:36:40');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` enum('cash','gcash','bank') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `event_id`, `amount_paid`, `payment_date`, `payment_method`) VALUES
(10, 14, 7, 300.00, '2026-05-08', 'cash'),
(12, 15, 8, 300.00, '2026-05-11', 'cash'),
(13, 15, 8, 200.00, '2026-05-11', 'cash');

-- --------------------------------------------------------

--
-- Table structure for table `payment_requests`
--

CREATE TABLE `payment_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `method` enum('cash','gcash','bank') DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_requests`
--

INSERT INTO `payment_requests` (`id`, `user_id`, `event_id`, `amount`, `method`, `status`, `created_at`) VALUES
(6, 6, 1, 200.00, 'cash', 'pending', '2026-04-23 13:52:29'),
(7, 6, 1, 200.00, 'cash', 'pending', '2026-04-23 13:53:22');

-- --------------------------------------------------------

--
-- Stand-in structure for view `payment_status`
-- (See below for the actual view)
--
CREATE TABLE `payment_status` (
`user_id` int(11)
,`name` varchar(100)
,`event_id` int(11)
,`event_name` varchar(150)
,`fee` decimal(10,2)
,`total_paid` decimal(32,2)
,`balance` decimal(33,2)
,`payment_status` varchar(7)
);

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `user_id`, `event_id`, `registration_date`) VALUES
(11, 14, 4, '2026-05-06 13:21:07'),
(13, 14, 7, '2026-05-08 11:20:48'),
(14, 15, 4, '2026-05-11 07:37:24'),
(15, 15, 8, '2026-05-11 07:38:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','member') DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `first_name` varchar(50) NOT NULL,
  `middle_initial` char(1) DEFAULT NULL,
  `surname` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `email_verified`, `first_name`, `middle_initial`, `surname`) VALUES
(1, 'Admin User', 'admin@gmail.com', '123456', 'admin', '2026-04-09 06:03:12', 0, '', NULL, ''),
(5, 'Zhanjianah T. Jaji', 'jaji.zhanjianahtabilin@gmail.com', '$2y$10$vqfRxKRcor8ziR5w/3SWuezwFkV2snG8ixWETiN4J6oGiU96Gs49O', 'member', '2026-04-18 06:43:09', 1, '', NULL, ''),
(14, 'yayay T. Tabilin', 'ZhanjianahTabilin@gmail.com', '$2y$10$ZiWias3gVgbPy8sdXE8am.5agxmpwso/SrqCPmp.IECPqWq4BhR2S', 'member', '2026-05-06 13:20:54', 1, 'yayay', 'T', 'Tabilin'),
(15, 'Maria D. Dela Cruz', 'AE202403298@wmsu.edu.ph', '$2y$10$s7I3b6hK3lv9SSPHxZ0GUu08shgZW9jD5vTgxiOxVVyR9w8DF/X2a', 'member', '2026-05-11 07:33:11', 1, 'Maria', 'D', 'Dela Cruz');

-- --------------------------------------------------------

--
-- Structure for view `payment_status`
--
DROP TABLE IF EXISTS `payment_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `payment_status`  AS SELECT `u`.`id` AS `user_id`, `u`.`name` AS `name`, `e`.`id` AS `event_id`, `e`.`event_name` AS `event_name`, `e`.`fee` AS `fee`, coalesce(sum(`p`.`amount_paid`),0) AS `total_paid`, `e`.`fee`- coalesce(sum(`p`.`amount_paid`),0) AS `balance`, CASE WHEN `e`.`is_free` = 1 THEN 'Free' WHEN sum(`p`.`amount_paid`) is null THEN 'Unpaid' WHEN sum(`p`.`amount_paid`) < `e`.`fee` THEN 'Partial' WHEN sum(`p`.`amount_paid`) >= `e`.`fee` THEN 'Paid' END FROM (((`users` `u` join `registrations` `r` on(`u`.`id` = `r`.`user_id`)) join `events` `e` on(`e`.`id` = `r`.`event_id`)) left join `payments` `p` on(`p`.`user_id` = `u`.`id` and `p`.`event_id` = `e`.`id` and `e`.`is_free` = 0)) GROUP BY `u`.`id`, `e`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `payment_requests`
--
ALTER TABLE `payment_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payment_requests`
--
ALTER TABLE `payment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
