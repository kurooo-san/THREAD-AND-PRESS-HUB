<?php
/**
 * Contact Database Configuration
 * Handles separate database connection for contact form submissions
 */

// Contact Database Configuration
define('CONTACT_DB_HOST', getenv('CONTACT_DB_HOST') ?: 'localhost');
define('CONTACT_DB_USER', getenv('CONTACT_DB_USER') ?: 'root');
define('CONTACT_DB_PASS', getenv('CONTACT_DB_PASS') ?: '');
define('CONTACT_DB_NAME', getenv('CONTACT_DB_NAME') ?: 'threadpresshub');

/**
 * Create and return contact database connection
 * @return mysqli
 */
function getContactDB() {
    static $contact_conn = null;
    
    if ($contact_conn === null) {
        $contact_conn = new mysqli(
            CONTACT_DB_HOST, 
            CONTACT_DB_USER, 
            CONTACT_DB_PASS, 
            CONTACT_DB_NAME
        );
        
        if ($contact_conn->connect_error) {
            die("Contact DB Connection failed: " . $contact_conn->connect_error);
        }
        
        $contact_conn->set_charset("utf8mb4");
    }
    
    return $contact_conn;
}

/**
 * Helper function to insert contact message
 * @param array $data - Message data
 * @return bool
 */
function insertContactMessage($data) {
    $contact_db = getContactDB();
    
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? null;
    $subject = $data['subject'] ?? null;
    $message = $data['message'] ?? '';
    $category = $data['category'] ?? 'general';
    $priority = $data['priority'] ?? 'normal';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $contact_db->prepare(
        "INSERT INTO contact_messages 
        (name, email, phone, subject, message, category, priority, ip_address, user_agent, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())"
    );
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param(
        "sssssssss",
        $name,
        $email,
        $phone,
        $subject,
        $message,
        $category,
        $priority,
        $ip_address,
        $user_agent
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get all contact messages (for admin)
 * @param string $status - Filter by status
 * @return array
 */
function getContactMessages($status = null) {
    $contact_db = getContactDB();
    
    $sql = "SELECT * FROM contact_messages WHERE 1=1";
    
    if ($status) {
        $sql .= " AND status = '" . $contact_db->real_escape_string($status) . "'";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $result = $contact_db->query($sql);
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get single contact message
 * @param int $id - Message ID
 * @return array|null
 */
function getContactMessage($id) {
    $contact_db = getContactDB();
    
    $stmt = $contact_db->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    $stmt->close();
    
    return $message;
}

/**
 * Update contact message status
 * @param int $id - Message ID
 * @param string $status - New status
 * @param int $admin_id - Admin user ID
 * @return bool
 */
function updateContactMessageStatus($id, $status, $admin_id = null) {
    $contact_db = getContactDB();
    
    $timestamp = null;
    if ($status === 'responded') {
        $timestamp = 'responded_at';
    } elseif ($status === 'closed') {
        $timestamp = 'closed_at';
    }
    
    $sql = "UPDATE contact_messages SET status = ?, assigned_to = ?";
    
    if ($timestamp) {
        $sql .= ", {$timestamp} = NOW()";
    }
    
    $sql .= " WHERE id = ?";
    
    $stmt = $contact_db->prepare($sql);
    $stmt->bind_param("sii", $status, $admin_id, $id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Add admin response to contact message
 * @param int $contact_id - Contact message ID
 * @param int $admin_id - Admin user ID
 * @param string $response - Response message
 * @return bool
 */
function addContactResponse($contact_id, $admin_id, $response) {
    $contact_db = getContactDB();
    
    $stmt = $contact_db->prepare(
        "INSERT INTO contact_messages_responses (contact_id, admin_id, response_message, created_at) 
        VALUES (?, ?, ?, NOW())"
    );
    
    $stmt->bind_param("iis", $contact_id, $admin_id, $response);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get contact categories
 * @return array
 */
function getContactCategories() {
    $contact_db = getContactDB();
    
    $result = $contact_db->query("SELECT * FROM contact_categories WHERE active = 1 ORDER BY name");
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
