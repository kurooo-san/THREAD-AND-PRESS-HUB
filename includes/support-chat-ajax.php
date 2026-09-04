<?php
require_once __DIR__ . '/support-chat-config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['user_type'] === 'admin');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_conversations':
        if ($isAdmin) {
            $conversations = getAllConversations($conn);
        } else {
            $conversations = getUserConversations($conn, $userId);
        }
        echo json_encode(['success' => true, 'conversations' => $conversations]);
        break;

    case 'create_conversation':
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        if (empty($subject) || empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Subject and message are required']);
            exit();
        }
        
        $convId = createConversation($conn, $userId, $subject);
        if ($convId) {
            sendMessage($conn, $convId, $userId, 'user', $message);
            echo json_encode(['success' => true, 'conversation_id' => $convId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create conversation']);
        }
        break;

    case 'send_message':
        $conversationId = intval($_POST['conversation_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if (!$conversationId) {
            echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
            exit();
        }
        
        // Verify access
        if ($isAdmin) {
            $conversation = getConversation($conn, $conversationId);
        } else {
            $conversation = getConversation($conn, $conversationId, $userId);
        }
        
        if (!$conversation || $conversation['status'] === 'closed') {
            echo json_encode(['success' => false, 'error' => 'Conversation not found or closed']);
            exit();
        }
        
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = handleSupportImageUpload($_FILES['image']);
            if ($upload['success']) {
                $imagePath = $upload['path'];
            } else {
                echo json_encode(['success' => false, 'error' => $upload['error']]);
                exit();
            }
        }
        
        if (empty($message) && empty($imagePath)) {
            echo json_encode(['success' => false, 'error' => 'Message or image required']);
            exit();
        }
        
        $senderType = $isAdmin ? 'admin' : 'user';
        $msgId = sendMessage($conn, $conversationId, $userId, $senderType, $message ?: null, $imagePath);
        
        echo json_encode([
            'success' => true,
            'message' => [
                'id' => $msgId,
                'sender_name' => $_SESSION['user_name'],
                'sender_type' => $senderType,
                'message' => $message,
                'image_path' => $imagePath,
                'created_at' => date('M d, g:i A')
            ]
        ]);
        break;
        
    case 'get_messages':
        $conversationId = intval($_GET['conversation_id'] ?? 0);
        $lastId = intval($_GET['last_id'] ?? 0);
        
        if (!$conversationId) {
            echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
            exit();
        }
        
        // Verify access
        if ($isAdmin) {
            $conversation = getConversation($conn, $conversationId);
        } else {
            $conversation = getConversation($conn, $conversationId, $userId);
        }
        
        if (!$conversation) {
            echo json_encode(['success' => false, 'error' => 'Conversation not found']);
            exit();
        }
        
        // Get new messages since last ID
        $stmt = $conn->prepare("
            SELECT sm.*, u.fullname as sender_name
            FROM support_messages sm
            JOIN users u ON sm.sender_id = u.id
            WHERE sm.conversation_id = ? AND sm.id > ?
            ORDER BY sm.created_at ASC
        ");
        $stmt->bind_param("ii", $conversationId, $lastId);
        $stmt->execute();
        $result = $stmt->get_result();
        $newMessages = [];
        while ($row = $result->fetch_assoc()) {
            $row['created_at_formatted'] = date('M d, g:i A', strtotime($row['created_at']));
            $newMessages[] = $row;
        }
        $stmt->close();
        
        // Mark incoming messages as read
        $markType = $isAdmin ? 'user' : 'admin';
        if (!empty($newMessages)) {
            markMessagesRead($conn, $conversationId, $markType);
        }
        
        echo json_encode(['success' => true, 'messages' => $newMessages]);
        break;
        
    case 'get_unread_count':
        if ($isAdmin) {
            $count = getAdminUnreadCount($conn);
        } else {
            $count = getUserUnreadCount($conn, $userId);
        }
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
