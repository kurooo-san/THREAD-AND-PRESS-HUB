<?php
require 'includes/support-chat-config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$pageTitle = 'Support Chat';

// Handle new conversation creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_conversation'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (!empty($subject) && !empty($message)) {
        $convId = createConversation($conn, $userId, $subject);
        
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = handleSupportImageUpload($_FILES['image']);
            if ($upload['success']) {
                $imagePath = $upload['path'];
            }
        }
        
        sendMessage($conn, $convId, $userId, 'user', $message, $imagePath);
        header("Location: support-chat.php?conversation=" . $convId);
        exit();
    }
}

$conversations = getUserConversations($conn, $userId);

$activeConversation = null;
$messages = [];
if (isset($_GET['conversation'])) {
    $convId = intval($_GET['conversation']);
    $activeConversation = getConversation($conn, $convId, $userId);
    if ($activeConversation) {
        $messages = getConversationMessages($conn, $convId);
        markMessagesRead($conn, $convId, 'admin');
    }
}
?>

<?php include 'includes/header/header.php'; ?>

<div class="support-chat-container">
    <div class="support-chat-wrapper">
        <!-- Sidebar: Conversation List -->
        <div class="support-sidebar" id="supportSidebar">
            <div class="support-sidebar-header">
                <h5><i class="fas fa-headset"></i> Support Chat</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newConversationModal">
                    <i class="fas fa-plus"></i> New
                </button>
            </div>
            <div class="support-conversation-list">
                <?php if (empty($conversations)): ?>
                    <div class="text-center text-muted p-4">
                        <i class="fas fa-comments fa-2x mb-2 d-block"></i>
                        No conversations yet.<br>Start one to chat with our team!
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <a href="support-chat.php?conversation=<?php echo $conv['id']; ?>" 
                           class="support-conv-item <?php echo ($activeConversation && $activeConversation['id'] == $conv['id']) ? 'active' : ''; ?>">
                            <div class="support-conv-header">
                                <span class="support-conv-subject"><?php echo htmlspecialchars($conv['subject']); ?></span>
                                <span class="support-conv-status badge bg-<?php echo $conv['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($conv['status']); ?>
                                </span>
                            </div>
                            <div class="support-conv-preview">
                                <?php echo htmlspecialchars(mb_strimwidth($conv['last_message'] ?? 'No messages', 0, 50, '...')); ?>
                            </div>
                            <div class="support-conv-meta">
                                <span class="support-conv-time">
                                    <?php echo $conv['last_message_at'] ? date('M d, g:i A', strtotime($conv['last_message_at'])) : date('M d, g:i A', strtotime($conv['created_at'])); ?>
                                </span>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="badge bg-danger support-unread-badge"><?php echo $conv['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="support-main-chat">
            <?php if ($activeConversation): ?>
                <!-- Chat Header -->
                <div class="support-chat-header">
                    <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="toggleSidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="support-chat-info">
                        <h6><?php echo htmlspecialchars($activeConversation['subject']); ?></h6>
                        <small class="text-muted">
                            <span class="badge bg-<?php echo $activeConversation['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($activeConversation['status']); ?>
                            </span>
                            &middot; Started <?php echo date('M d, Y', strtotime($activeConversation['created_at'])); ?>
                        </small>
                    </div>
                </div>

                <!-- Messages -->
                <div class="support-messages" id="supportMessages">
                    <?php foreach ($messages as $msg): ?>
                        <div class="support-msg <?php echo $msg['sender_type'] === 'user' ? 'support-msg-user' : 'support-msg-admin'; ?>" data-msg-id="<?php echo $msg['id']; ?>">
                            <div class="support-msg-bubble">
                                <div class="support-msg-sender">
                                    <i class="fas <?php echo $msg['sender_type'] === 'user' ? 'fa-user' : 'fa-user-shield'; ?>"></i>
                                    <?php echo htmlspecialchars($msg['sender_name']); ?>
                                    <?php if ($msg['sender_type'] === 'admin'): ?>
                                        <span class="badge bg-primary ms-1" style="font-size: 0.65rem;">Admin</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($msg['image_path']): ?>
                                    <div class="support-msg-image">
                                        <a href="<?php echo htmlspecialchars($msg['image_path']); ?>" target="_blank">
                                            <img src="<?php echo htmlspecialchars($msg['image_path']); ?>" alt="Shared image" loading="lazy">
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($msg['message']): ?>
                                    <div class="support-msg-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <?php endif; ?>
                                <div class="support-msg-time">
                                    <?php echo date('M d, g:i A', strtotime($msg['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Input Area -->
                <?php if ($activeConversation['status'] === 'open'): ?>
                    <div class="support-input-area">
                        <form id="supportChatForm" enctype="multipart/form-data">
                            <input type="hidden" name="conversation_id" value="<?php echo $activeConversation['id']; ?>">
                            <div class="support-input-wrapper">
                                <div class="support-image-preview" id="imagePreview" style="display: none;">
                                    <img id="previewImg" src="" alt="Preview">
                                    <button type="button" class="btn-close" id="removeImage"></button>
                                </div>
                                <div class="support-input-row">
                                    <label class="support-attach-btn" for="chatImage" title="Attach image">
                                        <i class="fas fa-image"></i>
                                    </label>
                                    <input type="file" id="chatImage" name="image" accept="image/*" style="display: none;">
                                    <textarea id="chatMessage" name="message" placeholder="Type your message..." rows="1"></textarea>
                                    <button type="submit" class="support-send-btn" id="sendBtn">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="support-closed-notice">
                        <i class="fas fa-lock"></i> This conversation has been closed.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- No conversation selected -->
                <div class="support-empty-state">
                    <button class="btn btn-sm btn-outline-secondary d-md-none mb-3" id="toggleSidebarEmpty">
                        <i class="fas fa-bars"></i> View Conversations
                    </button>
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Select a conversation</h5>
                    <p class="text-muted">Choose an existing conversation from the sidebar or start a new one.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newConversationModal">
                        <i class="fas fa-plus"></i> Start New Conversation
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Conversation Modal -->
<div class="modal fade" id="newConversationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="support-chat.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header" style="background: var(--coffee-dark); color: white;">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> New Conversation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. Custom Design Request, Order Inquiry..." required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Describe what you need help with..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Attach Image (optional)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Max 5MB. JPEG, PNG, GIF, WebP only.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="new_conversation" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Start Conversation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/support-chat.js"></script>

<?php include 'includes/footer/footer.php'; ?>
