-- Support Chat System Migration
-- Run this SQL in phpMyAdmin or MySQL CLI to create the support chat tables

CREATE TABLE IF NOT EXISTS support_conversations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('open', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_status (status),
    CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS support_messages (
    id INT(11) NOT NULL AUTO_INCREMENT,
    conversation_id INT(11) NOT NULL,
    sender_id INT(11) NOT NULL,
    sender_type ENUM('user', 'admin') NOT NULL,
    message TEXT DEFAULT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conversation_id (conversation_id),
    KEY idx_sender (sender_id, sender_type),
    CONSTRAINT fk_support_conversation FOREIGN KEY (conversation_id) REFERENCES support_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
