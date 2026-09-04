-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 10, 2026 at 03:35 AM
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
-- Database: `threadpresshub_contact`
--

-- --------------------------------------------------------

--
-- Table structure for table `contact_categories`
--

CREATE TABLE `contact_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Category name',
  `description` text DEFAULT NULL COMMENT 'Category description',
  `active` tinyint(1) DEFAULT 1 COMMENT 'Is category active?'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_categories`
--

INSERT INTO `contact_categories` (`id`, `name`, `description`, `active`) VALUES
(1, 'General Inquiry', 'General questions about our services', 1),
(2, 'Product Support', 'Questions about specific products', 1),
(3, 'Order Issue', 'Problems with existing orders', 1),
(4, 'Payment Issue', 'Payment-related concerns', 1),
(5, 'Shipping Inquiry', 'Delivery and shipping questions', 1),
(6, 'Feedback', 'General feedback or suggestions', 1),
(7, 'Bug Report', 'Technical issues/bugs to report', 1),
(8, 'Business Partnership', 'Partnership and collaboration inquiries', 1),
(9, 'Return/Refund', 'Return and refund requests', 1),
(10, 'Other', 'Other inquiries', 1);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'Sender name',
  `email` varchar(150) NOT NULL COMMENT 'Sender email',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Optional phone number',
  `subject` varchar(255) DEFAULT NULL COMMENT 'Message subject',
  `message` longtext NOT NULL COMMENT 'Contact message',
  `category` varchar(50) DEFAULT 'general' COMMENT 'Message category',
  `priority` varchar(20) DEFAULT 'normal' COMMENT 'Priority level (low, normal, high, urgent)',
  `status` varchar(50) DEFAULT 'new' COMMENT 'Status (new, read, responded, closed)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Sender IP address',
  `user_agent` text DEFAULT NULL COMMENT 'Browser info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When submitted',
  `responded_at` timestamp NULL DEFAULT NULL COMMENT 'When admin responded',
  `closed_at` timestamp NULL DEFAULT NULL COMMENT 'When ticket was closed',
  `admin_notes` longtext DEFAULT NULL COMMENT 'Internal admin notes',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Admin user ID assigned to handle this'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `subject`, `message`, `category`, `priority`, `status`, `ip_address`, `user_agent`, `created_at`, `responded_at`, `closed_at`, `admin_notes`, `assigned_to`) VALUES
(1, 'Juan Dela Cruz', 'juan@example.com', '+63 912 345 6789', 'Interested in Custom Printing', 'I am interested in custom printing services for our company event. Can you provide more information about bulk orders and pricing?', 'Business Partnership', 'high', 'responded', '192.168.1.100', NULL, '2026-03-10 02:16:36', '2026-03-10 02:32:05', NULL, 'asqadddaSSD', 3),
(2, 'Maria Santos', 'maria@example.com', '+63 917 123 4567', 'Order Delivery Question', 'My order ID is 5. When will it be delivered? Have not received any updates yet.', 'Order Issue', 'normal', 'new', '192.168.1.101', NULL, '2026-03-10 02:16:36', NULL, NULL, NULL, NULL),
(3, 'Carlos Reyes', 'carlos@example.com', NULL, 'Product Quality Feedback', 'I received my order yesterday and I\'m very happy with the quality of the products! Great work!', 'Feedback', 'low', 'new', '192.168.1.102', NULL, '2026-03-10 02:16:36', NULL, NULL, NULL, NULL),
(4, 'Raymond Ligayaf', 'admin@apparelstudio.com', '09212856506', 'asad', 'ang lopit idols', 'Feedback', 'high', 'responded', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 02:18:22', '2026-03-10 02:31:24', NULL, 'asdasddasdasd', 3);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages_responses`
--

CREATE TABLE `contact_messages_responses` (
  `id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL COMMENT 'Foreign key to contact_messages',
  `admin_id` int(11) NOT NULL COMMENT 'Admin user who responded',
  `response_message` longtext NOT NULL COMMENT 'Admin response',
  `attachments` varchar(500) DEFAULT NULL COMMENT 'File attachments (JSON)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages_responses`
--

INSERT INTO `contact_messages_responses` (`id`, `contact_id`, `admin_id`, `response_message`, `attachments`, `created_at`) VALUES
(1, 4, 3, 'ganon ba salamat idols', NULL, '2026-03-10 02:31:24'),
(2, 1, 3, 'ok\r\n', NULL, '2026-03-10 02:32:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contact_categories`
--
ALTER TABLE `contact_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_assigned_to` (`assigned_to`);

--
-- Indexes for table `contact_messages_responses`
--
ALTER TABLE `contact_messages_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact_id` (`contact_id`),
  ADD KEY `idx_admin_id` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contact_categories`
--
ALTER TABLE `contact_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contact_messages_responses`
--
ALTER TABLE `contact_messages_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contact_messages_responses`
--
ALTER TABLE `contact_messages_responses`
  ADD CONSTRAINT `fk_contact_message` FOREIGN KEY (`contact_id`) REFERENCES `contact_messages` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
