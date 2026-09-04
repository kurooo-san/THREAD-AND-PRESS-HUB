-- AI Print Artwork Migration
-- Stores the standalone print design (no garment) that the AI Product Generator
-- produces alongside the catalog photo, so an admin can download the actual file
-- a printer needs instead of only the flattened product mockup.
--
-- Run this SQL in phpMyAdmin or MySQL CLI.
-- Safe to skip: the app keeps working without this column, it just cannot store
-- or offer the print file.

ALTER TABLE products
    ADD COLUMN design_asset VARCHAR(255) DEFAULT NULL AFTER image;
