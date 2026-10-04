-- Support chat tables were created as utf8 (3-byte), so a message containing
-- an emoji failed with "Incorrect string value". utf8mb4 is a superset:
-- existing rows convert without loss.
ALTER TABLE support_conversations CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE support_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
