<?php
require '../includes/support-chat-config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$adminId = $_SESSION['user_id'];
$pageTitle = 'Support Chat Management';

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $convId = intval($_POST['conversation_id']);
    $newStatus = $_POST['new_status'] === 'closed' ? 'closed' : 'open';
    updateConversationStatus($conn, $convId, $newStatus);
    header("Location: support-chat.php?conversation=" . $convId);
    exit();
}

$statusFilter = $_GET['status'] ?? null;
$conversations = getAllConversations($conn, $statusFilter);

$activeConversation = null;
$messages = [];
if (isset($_GET['conversation'])) {
    $convId = intval($_GET['conversation']);
    $activeConversation = getConversation($conn, $convId);
    if ($activeConversation) {
        $messages = getConversationMessages($conn, $convId);
        markMessagesRead($conn, $convId, 'user');
    }
}

$totalUnread = getAdminUnreadCount($conn);
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="support-chat-container admin-support">
    <div class="support-chat-wrapper">
        <!-- Sidebar: All Conversations -->
        <div class="support-sidebar" id="supportSidebar">
            <div class="support-sidebar-header">
                <h5>
                    <i class="fas fa-headset"></i> Support
                    <?php if ($totalUnread > 0): ?>
                        <span class="badge bg-danger"><?php echo $totalUnread; ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <!-- Filter -->
            <div class="support-filter px-3 py-2">
                <div class="btn-group btn-group-sm w-100">
                    <a href="support-chat.php" class="btn <?php echo !$statusFilter ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    <a href="support-chat.php?status=open" class="btn <?php echo $statusFilter === 'open' ? 'btn-primary' : 'btn-outline-primary'; ?>">Open</a>
                    <a href="support-chat.php?status=closed" class="btn <?php echo $statusFilter === 'closed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Closed</a>
                </div>
            </div>
            <div class="support-conversation-list">
                <?php if (empty($conversations)): ?>
                    <div class="text-center text-muted p-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        No conversations found.
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <a href="support-chat.php?conversation=<?php echo $conv['id']; ?><?php echo $statusFilter ? '&status=' . $statusFilter : ''; ?>" 
                           class="support-conv-item <?php echo ($activeConversation && $activeConversation['id'] == $conv['id']) ? 'active' : ''; ?>">
                            <div class="support-conv-header">
                                <span class="support-conv-subject"><?php echo htmlspecialchars($conv['subject']); ?></span>
                                <span class="support-conv-status badge bg-<?php echo $conv['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($conv['status']); ?>
                                </span>
                            </div>
                            <div class="support-conv-user">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($conv['fullname']); ?>
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
                    <div class="support-chat-info flex-grow-1">
                        <h6><?php echo htmlspecialchars($activeConversation['subject']); ?></h6>
                        <small class="text-muted">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($activeConversation['fullname']); ?> 
                            (<?php echo htmlspecialchars($activeConversation['email']); ?>)
                            &middot;
                            <span class="badge bg-<?php echo $activeConversation['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($activeConversation['status']); ?>
                            </span>
                        </small>
                    </div>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="conversation_id" value="<?php echo $activeConversation['id']; ?>">
                        <?php if ($activeConversation['status'] === 'open'): ?>
                            <input type="hidden" name="new_status" value="closed">
                            <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-danger" title="Close conversation">
                                <i class="fas fa-lock"></i> Close
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="new_status" value="open">
                            <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-success" title="Reopen conversation">
                                <i class="fas fa-lock-open"></i> Reopen
                            </button>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Messages -->
                <div class="support-messages" id="supportMessages">
                    <?php foreach ($messages as $msg): ?>
                        <div class="support-msg <?php echo $msg['sender_type'] === 'admin' ? 'support-msg-user' : 'support-msg-admin'; ?>" data-msg-id="<?php echo $msg['id']; ?>">
                            <div class="support-msg-bubble">
                                <div class="support-msg-sender">
                                    <i class="fas <?php echo $msg['sender_type'] === 'user' ? 'fa-user' : 'fa-user-shield'; ?>"></i>
                                    <?php echo htmlspecialchars($msg['sender_name']); ?>
                                    <?php if ($msg['sender_type'] === 'admin'): ?>
                                        <span class="badge bg-primary ms-1" style="font-size: 0.65rem;">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-info ms-1" style="font-size: 0.65rem;">Customer</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($msg['image_path']): ?>
                                    <div class="support-msg-image">
                                        <a href="../<?php echo htmlspecialchars($msg['image_path']); ?>" target="_blank">
                                            <img src="../<?php echo htmlspecialchars($msg['image_path']); ?>" alt="Shared image" loading="lazy">
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($msg['message']): ?>
                                    <div class="support-msg-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <?php endif; ?>
                                <div class="support-msg-time">
                                    <?php echo date('M d, g:i A', strtotime($msg['created_at'])); ?>
                                    <?php if ($msg['is_read']): ?>
                                        <i class="fas fa-check-double text-primary ms-1" title="Read"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Admin Input -->
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
                                    <label class="support-attach-btn" for="chatImage" title="Attach image (final design, etc.)">
                                        <i class="fas fa-image"></i>
                                    </label>
                                    <input type="file" id="chatImage" name="image" accept="image/*" style="display: none;">
                                    <textarea id="chatMessage" name="message" placeholder="Reply to customer..." rows="1"></textarea>
                                    <button type="submit" class="support-send-btn" id="sendBtn">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="support-closed-notice">
                        <i class="fas fa-lock"></i> This conversation is closed. Reopen it to reply.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- No conversation selected -->
                <div class="support-empty-state">
                    <button class="btn btn-sm btn-outline-secondary d-md-none mb-3" id="toggleSidebarEmpty">
                        <i class="fas fa-bars"></i> View Conversations
                    </button>
                    <i class="fas fa-headset fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Support Chat Management</h5>
                    <p class="text-muted">Select a conversation from the sidebar to view and respond to customer messages.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Set admin mode for the JS
    window.SUPPORT_CHAT_ADMIN = true;
    window.SUPPORT_CHAT_AJAX_URL = '../includes/support-chat-ajax.php';
</script>
<script src="../js/support-chat.js"></script>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
