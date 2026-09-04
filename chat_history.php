<?php
// chat_history.php - View your chat history with the AI chatbot
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Chat History';
$userId    = (int)$_SESSION['user_id'];
$userName  = $_SESSION['user_name'] ?? ($_SESSION['fullname'] ?? 'User');

// Get chat history for the logged-in user
$stmt = $conn->prepare("SELECT id, user_message, bot_response, created_at FROM chat_history WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$chatMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalMessages = count($chatMessages);
?>

<?php include 'includes/header/header.php'; ?>

<style>
    .chat-history-page { max-width: 900px; margin: 30px auto; }
    .chat-history-page .chat-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff; padding: 25px; border-radius: 12px 12px 0 0;
    }
    .chat-history-page .chat-header h2 { margin: 0; display: flex; align-items: center; gap: 10px; }
    .chat-history-page .chat-stats { font-size: 14px; opacity: 0.95; margin-top: 10px; }
    .chat-history-page .chat-count { background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 13px; display: inline-block; margin-top: 10px; }
    .chat-history-page .chat-content { background: #fff; border: 1px solid #e9ecef; border-radius: 0 0 12px 12px; padding: 20px; min-height: 400px; }
    .chat-history-page .message-item { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e9ecef; }
    .chat-history-page .message-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .chat-history-page .user-message { background: #e7f3ff; border-left: 4px solid #667eea; padding: 15px; border-radius: 8px; margin-bottom: 12px; }
    .chat-history-page .bot-message  { background: #f0f0f0; border-left: 4px solid #764ba2; padding: 15px; border-radius: 8px; }
    .chat-history-page .user-label, .chat-history-page .bot-label { font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
    .chat-history-page .user-label { color: #667eea; }
    .chat-history-page .bot-label  { color: #764ba2; }
    .chat-history-page .message-timestamp { font-size: 12px; color: #6c757d; margin-top: 10px; }
    .chat-history-page .message-content { word-wrap: break-word; line-height: 1.6; color: #212529; }
    .chat-history-page .user-message .message-content,
    .chat-history-page .bot-message .message-content { color: #212529; }
    .chat-history-page .empty-state { text-align: center; padding: 50px 20px; color: #6c757d; }
    .chat-history-page .empty-state i { font-size: 48px; color: #dee2e6; margin-bottom: 20px; }
</style>

<div class="container chat-history-page">
    <a href="profile.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left"></i> Back to Profile
    </a>

    <div class="chat-header">
        <h2><i class="fas fa-comments"></i> Chat History</h2>
        <div class="chat-stats">
            <p class="mb-0">Welcome, <strong><?php echo htmlspecialchars($userName); ?></strong></p>
            <div class="chat-count">
                <i class="fas fa-message"></i> Total Conversations: <strong><?php echo (int)$totalMessages; ?></strong>
            </div>
        </div>
    </div>

    <div class="chat-content">
        <?php if (empty($chatMessages)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h5>No Chat History Yet</h5>
                <p>You haven't had any conversations with our AI chatbot yet.</p>
                <p>Start chatting with our support bot to get help with your questions!</p>
                <a href="index.php" class="btn btn-primary btn-sm mt-3">
                    <i class="fas fa-comments"></i> Start Chatting
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($chatMessages as $message): ?>
                <div class="message-item">
                    <div class="user-message">
                        <div class="user-label"><i class="fas fa-user-circle"></i> You</div>
                        <div class="message-content"><?php echo htmlspecialchars($message['user_message']); ?></div>
                        <div class="message-timestamp">
                            <i class="fas fa-clock"></i> <?php echo date('M d, Y - h:i A', strtotime($message['created_at'])); ?>
                        </div>
                    </div>
                    <?php if (!empty($message['bot_response'])): ?>
                        <div class="bot-message">
                            <div class="bot-label"><i class="fas fa-robot"></i> AI Assistant</div>
                            <div class="message-content"><?php echo nl2br(htmlspecialchars($message['bot_response'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>
