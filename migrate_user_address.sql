-- Address Fields Migration for Users Table
-- Thread & Press Hub - Registration System Update

ALTER TABLE users
    ADD COLUMN street_address VARCHAR(255) DEFAULT NULL AFTER senior_id,
    ADD COLUMN barangay VARCHAR(100) DEFAULT NULL AFTER street_address,
    ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER barangay,
    ADD COLUMN province VARCHAR(100) DEFAULT NULL AFTER city,
    ADD COLUMN zipcode VARCHAR(20) DEFAULT NULL AFTER province;
