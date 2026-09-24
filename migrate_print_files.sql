-- =====================================================================
-- Print-ready artwork for custom designs
--
-- Run once:  mysql -u root threadpresshub < migrate_print_files.sql
--
-- design_image / design_image_back are MOCKUPS: the garment with the
-- artwork composited on top. A printer cannot use those -- they would
-- print the t-shirt drawing as well.
--
-- print_front / print_back store the artwork layer on its own, cropped to
-- the printable area and on a transparent background, so the file can go
-- straight to DTG or screen printing.
--
-- Both are nullable: designs saved before this feature simply have no
-- print file, and the admin screen says so rather than offering a broken
-- download.
-- =====================================================================

ALTER TABLE custom_designs
    ADD COLUMN print_front VARCHAR(255) DEFAULT NULL AFTER design_image_back,
    ADD COLUMN print_back  VARCHAR(255) DEFAULT NULL AFTER print_front;
