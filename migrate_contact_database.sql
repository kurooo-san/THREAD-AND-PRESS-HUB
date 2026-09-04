-- Contact Form Submissions Database
-- Separate database: threadpresshub_contact

CREATE DATABASE IF NOT EXISTS `threadpresshub_contact` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `threadpresshub_contact`;

-- =========================================================
-- Table: contact_messages
-- Purpose: Store all contact form submissions
-- =========================================================

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'Sender name',
  `email` varchar(150) NOT NULL COMMENT 'Sender email',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Optional phone number',
  `subject` varchar(255) DEFAULT NULL COMMENT 'Message subject',
  `message` longtext NOT NULL COMMENT 'Contact message',
  `category` varchar(50) DEFAULT 'general' COMMENT 'Message category',
  `priority` varchar(20) DEFAULT 'normal' COMMENT 'Priority level (low, normal, high, urgent)',
  `status` varchar(50) DEFAULT 'new' COMMENT 'Status (new, read, responded, closed)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Sender IP address',
  `user_agent` text COMMENT 'Browser info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When submitted',
  `responded_at` timestamp NULL COMMENT 'When admin responded',
  `closed_at` timestamp NULL COMMENT 'When ticket was closed',
  `admin_notes` longtext COMMENT 'Internal admin notes',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Admin user ID assigned to handle this',
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Table: contact_categories
-- Purpose: Predefined contact message categories
-- =========================================================

CREATE TABLE IF NOT EXISTS `contact_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Category name',
  `description` text COMMENT 'Category description',
  `active` tinyint(1) DEFAULT 1 COMMENT 'Is category active?',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO `contact_categories` (`name`, `description`, `active`) VALUES
('General Inquiry', 'General questions about our services', 1),
('Product Support', 'Questions about specific products', 1),
('Order Issue', 'Problems with existing orders', 1),
('Payment Issue', 'Payment-related concerns', 1),
('Shipping Inquiry', 'Delivery and shipping questions', 1),
('Feedback', 'General feedback or suggestions', 1),
('Bug Report', 'Technical issues/bugs to report', 1),
('Business Partnership', 'Partnership and collaboration inquiries', 1),
('Return/Refund', 'Return and refund requests', 1),
('Other', 'Other inquiries', 1);

-- =========================================================
-- Table: contact_messages_responses
-- Purpose: Track admin responses to contact messages
-- =========================================================

CREATE TABLE IF NOT EXISTS `contact_messages_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL COMMENT 'Foreign key to contact_messages',
  `admin_id` int(11) NOT NULL COMMENT 'Admin user who responded',
  `response_message` longtext NOT NULL COMMENT 'Admin response',
  `attachments` varchar(500) DEFAULT NULL COMMENT 'File attachments (JSON)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_admin_id` (`admin_id`),
  CONSTRAINT `fk_contact_message` FOREIGN KEY (`contact_id`) REFERENCES `contact_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Sample Data (for testing)
-- =========================================================

INSERT INTO `contact_messages` (`name`, `email`, `phone`, `subject`, `message`, `category`, `priority`, `status`, `ip_address`, `created_at`) VALUES
('Juan Dela Cruz', 'juan@example.com', '+63 912 345 6789', 'Interested in Custom Printing', 'I am interested in custom printing services for our company event. Can you provide more information about bulk orders and pricing?', 'Business Partnership', 'high', 'new', '192.168.1.100', NOW()),
('Maria Santos', 'maria@example.com', '+63 917 123 4567', 'Order Delivery Question', 'My order ID is 5. When will it be delivered? Have not received any updates yet.', 'Order Issue', 'normal', 'new', '192.168.1.101', NOW()),
('Carlos Reyes', 'carlos@example.com', NULL, 'Product Quality Feedback', 'I received my order yesterday and I\'m very happy with the quality of the products! Great work!', 'Feedback', 'low', 'new', '192.168.1.102', NOW());

-- =========================================================
-- Database Creation Summary
-- =========================================================
-- Database Name: threadpresshub_contact
-- Tables: 3
--   1. contact_messages (main contact submissions)
--   2. contact_categories (category definitions)
--   3. contact_messages_responses (admin responses)
-- 
-- Features:
-- ✅ Full-featured contact management
-- ✅ Status tracking (new, read, responded, closed)
-- ✅ Priority levels (low, normal, high, urgent)
-- ✅ Admin assignment & notes
-- ✅ Response tracking with timestamps
-- ✅ IP tracking for security
-- ✅ 10 predefined categories
-- ✅ Proper indexing for performance
-- 
-- Usage:
-- 1. Import this file in phpMyAdmin
-- 2. Update includes/config.php with new database config
-- 3. Update contact.php to use new database
-- 4. Create admin panel for managing messages
-- =========================================================
