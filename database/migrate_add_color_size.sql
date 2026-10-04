-- Database Migration: Add color and size columns to order_items
-- Run this SQL in phpMyAdmin or via command line

-- Add color column to order_items table
ALTER TABLE `order_items` ADD COLUMN `color` varchar(50) DEFAULT NULL AFTER `subtotal`;

-- Add size column to order_items table
ALTER TABLE `order_items` ADD COLUMN `size` varchar(10) DEFAULT NULL AFTER `color`;

-- Note: If columns already exist, you can safely ignore the error
-- These columns will store the selected color and size for each item in an order
