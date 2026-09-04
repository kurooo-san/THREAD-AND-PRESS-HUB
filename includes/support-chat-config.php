<?php
require_once __DIR__ . '/config.php';

/**
 * Support Chat Configuration & Helper Functions
 */

// Allowed image types and max size (5MB)
define('SUPPORT_UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('SUPPORT_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('SUPPORT_UPLOAD_DIR', __DIR__ . '/../uploads/support/');

/**
 * Ensure the upload directory exists
 */
function ensureSupportUploadDir()
{
    if (!is_dir(SUPPORT_UPLOAD_DIR)) {
        mkdir(SUPPORT_UPLOAD_DIR, 0755, true);
    }
}

/**
 * Create a new support conversation
 */
function createConversation($conn, $userId, $subject)
{
    $stmt = $conn->prepare("INSERT INTO support_conversations (user_id, subject) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $subject);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Send a message in a conversation
 */
function sendMessage($conn, $conversationId, $senderId, $senderType, $message = null, $imagePath = null)
{
    $stmt = $conn->prepare("INSERT INTO support_messages (conversation_id, sender_id, sender_type, message, image_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $conversationId, $senderId, $senderType, $message, $imagePath);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();

    // Update conversation timestamp
    $stmt2 = $conn->prepare("UPDATE support_conversations SET updated_at = NOW() WHERE id = ?");
    $stmt2->bind_param("i", $conversationId);
    $stmt2->execute();
    $stmt2->close();

    return $id;
}

/**
 * Get all conversations for a user
 */
function getUserConversations($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT sc.*,
               (SELECT COUNT(*) FROM support_messages sm WHERE sm.conversation_id = sc.id AND sm.sender_type = 'admin' AND sm.is_read = 0) as unread_count,
               (SELECT sm2.message FROM support_messages sm2 WHERE sm2.conversation_id = sc.id ORDER BY sm2.created_at DESC LIMIT 1) as last_message,
               (SELECT sm3.created_at FROM support_messages sm3 WHERE sm3.conversation_id = sc.id ORDER BY sm3.created_at DESC LIMIT 1) as last_message_at
        FROM support_conversations sc
        WHERE sc.user_id = ?
        ORDER BY sc.updated_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    $stmt->close();
    return $conversations;
}

/**
 * Get all conversations for admin view
 */
function getAllConversations($conn, $status = null)
{
    $sql = "
        SELECT sc.*, u.fullname, u.email,
               (SELECT COUNT(*) FROM support_messages sm WHERE sm.conversation_id = sc.id AND sm.sender_type = 'user' AND sm.is_read = 0) as unread_count,
               (SELECT sm2.message FROM support_messages sm2 WHERE sm2.conversation_id = sc.id ORDER BY sm2.created_at DESC LIMIT 1) as last_message,
               (SELECT sm3.created_at FROM support_messages sm3 WHERE sm3.conversation_id = sc.id ORDER BY sm3.created_at DESC LIMIT 1) as last_message_at
        FROM support_conversations sc
        JOIN users u ON sc.user_id = u.id
    ";
    if ($status) {
        $sql .= " WHERE sc.status = ?";
    }
    $sql .= " ORDER BY sc.updated_at DESC";

    $stmt = $conn->prepare($sql);
    if ($status) {
        $stmt->bind_param("s", $status);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    $stmt->close();
    return $conversations;
}

/**
 * Get messages for a conversation
 */
function getConversationMessages($conn, $conversationId)
{
    $stmt = $conn->prepare("
        SELECT sm.*, u.fullname as sender_name
        FROM support_messages sm
        JOIN users u ON sm.sender_id = u.id
        WHERE sm.conversation_id = ?
        ORDER BY sm.created_at ASC
    ");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    return $messages;
}

/**
 * Mark messages as read
 */
function markMessagesRead($conn, $conversationId, $senderType)
{
    $stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = ?");
    $stmt->bind_param("is", $conversationId, $senderType);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get a single conversation with permission check
 */
function getConversation($conn, $conversationId, $userId = null)
{
    $sql = "SELECT sc.*, u.fullname, u.email FROM support_conversations sc JOIN users u ON sc.user_id = u.id WHERE sc.id = ?";
    if ($userId) {
        $sql .= " AND sc.user_id = ?";
    }
    $stmt = $conn->prepare($sql);
    if ($userId) {
        $stmt->bind_param("ii", $conversationId, $userId);
    } else {
        $stmt->bind_param("i", $conversationId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $conversation = $result->fetch_assoc();
    $stmt->close();
    return $conversation;
}

/**
 * Close/reopen a conversation
 */
function updateConversationStatus($conn, $conversationId, $status)
{
    $stmt = $conn->prepare("UPDATE support_conversations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $conversationId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Handle image upload for support chat
 */
function handleSupportImageUpload($file)
{
    ensureSupportUploadDir();

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed'];
    }

    if ($file['size'] > SUPPORT_UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'File too large. Max 5MB allowed'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, SUPPORT_ALLOWED_TYPES)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, WebP allowed'];
    }

    $ext = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    $filename = 'support_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destination = SUPPORT_UPLOAD_DIR . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'path' => 'uploads/support/' . $filename];
    }

    return ['success' => false, 'error' => 'Failed to save file'];
}

/**
 * Get unread message count for admin
 */
function getAdminUnreadCount($conn)
{
    $result = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE sender_type = 'user' AND is_read = 0");
    return $result->fetch_assoc()['count'];
}

/**
 * Get unread message count for a specific user
 */
function getUserUnreadCount($conn, $userId)
{
    $result = $conn->query("
        SELECT COUNT(*) as count FROM support_messages sm
        JOIN support_conversations sc ON sm.conversation_id = sc.id
        WHERE sc.user_id = " . intval($userId) . " AND sm.sender_type = 'admin' AND sm.is_read = 0
    ");
    return $result->fetch_assoc()['count'];
}
