<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/contact-config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'Contact Management';

// Handle actions
$action = $_GET['action'] ?? null;
$contact_id = intval($_GET['id'] ?? 0);

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $contact_id = intval($_POST['id']);
        $status = trim($_POST['status']);
        $admin_id = $_SESSION['user_id'];
        
        updateContactMessageStatus($contact_id, $status, $admin_id);
        
        // Add notes if provided
        if (!empty($_POST['admin_notes'])) {
            $contact_db = getContactDB();
            $notes = trim($_POST['admin_notes']);
            $contact_db->query(
                "UPDATE contact_messages SET admin_notes = '" . 
                $contact_db->real_escape_string($notes) . 
                "' WHERE id = " . $contact_id
            );
        }
        
        // Add response if provided
        if (!empty($_POST['response'])) {
            addContactResponse($contact_id, $admin_id, $_POST['response']);
            updateContactMessageStatus($contact_id, 'responded', $admin_id);
        }
        
        $_SESSION['success'] = "✅ Contact message updated!";
        header("Location: contact-management.php?id=" . $contact_id);
        exit();
    } elseif ($_POST['action'] === 'delete') {
        $contact_id = intval($_POST['id']);
        $contact_db = getContactDB();
        $contact_db->query("DELETE FROM contact_messages WHERE id = " . $contact_id);
        $_SESSION['success'] = "✅ Message deleted!";
        header("Location: contact-management.php");
        exit();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? null;
$category_filter = $_GET['category'] ?? null;

// Get all messages or single message
if ($action === 'view' && $contact_id > 0) {
    $message = getContactMessage($contact_id);
    $view_mode = 'single';
} else {
    $contact_db = getContactDB();
    
    $sql = "SELECT * FROM contact_messages WHERE 1=1";
    
    if ($status_filter) {
        $sql .= " AND status = '" . $contact_db->real_escape_string($status_filter) . "'";
    }
    if ($category_filter) {
        $sql .= " AND category = '" . $contact_db->real_escape_string($category_filter) . "'";
    }
    
    $sql .= " ORDER BY 
        CASE priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'low' THEN 4 
        END ASC,
        created_at DESC";
    
    $messages = $contact_db->query($sql)->fetch_all(MYSQLI_ASSOC);
    $view_mode = 'list';
    
    // Get stats
    $stats = [
        'total' => $contact_db->query("SELECT COUNT(*) as count FROM contact_messages")->fetch_assoc()['count'],
        'new' => $contact_db->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'new'")->fetch_assoc()['count'],
        'responded' => $contact_db->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'responded'")->fetch_assoc()['count'],
        'closed' => $contact_db->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'closed'")->fetch_assoc()['count'],
    ];
}

$categories = getContactCategories();
?>

<?php include __DIR__ . '/../includes/header/header.php'; ?>
<?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

<style>
    .message-card {
        background: white;
        border-left: 4px solid var(--primary);
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .message-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .badge-status { padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
    .badge-new { background: #cfe2ff; color: #084298; }
    .badge-read { background: #e2e3e5; color: #383d41; }
    .badge-responded { background: #d1e7dd; color: #0a3622; }
    .badge-closed { background: #d3d5d6; color: #383d41; }
    .badge-priority-urgent { background: #f8d7da; color: #842029; }
    .badge-priority-high { background: #fff3cd; color: #664d03; }
    .badge-priority-normal { background: #d1ecf1; color: #055160; }
    .badge-priority-low { background: #e2e3e5; color: #383d41; }
    .stat-box { text-align: center; padding: 15px; border-radius: 8px; background: var(--bg-light); }
    .stat-number { font-size: 2rem; font-weight: 700; color: var(--primary); }
</style>

<div class="admin-container">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-envelope"></i> Contact Management
        </h1>
        <p class="text-muted">Manage customer contact form submissions</p>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($view_mode === 'list'): ?>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="admin-card text-center">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card text-center">
                <div class="stat-number" style="color: #dc3545;"><?php echo $stats['new']; ?></div>
                <div class="stat-label">New/Unread</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card text-center">
                <div class="stat-number" style="color: #007bff;"><?php echo $stats['responded']; ?></div>
                <div class="stat-label">Responded</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card text-center">
                <div class="stat-number" style="color: #28a745;"><?php echo $stats['closed']; ?></div>
                <div class="stat-label">Closed</div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="admin-card mb-4">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Filters</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select class="form-control" name="status">
                    <option value="">All Statuses</option>
                    <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                    <option value="responded" <?php echo $status_filter === 'responded' ? 'selected' : ''; ?>>Responded</option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select class="form-control" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                        <?php echo $category_filter === $cat['name'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Messages List -->
    <div class="admin-card">
        <h5 class="mb-3"><i class="fas fa-list"></i> Messages (<?php echo count($messages); ?>)</h5>
        
        <?php if (count($messages) > 0): ?>
            <?php foreach ($messages as $msg): ?>
            <div class="message-card" onclick="window.location.href='contact-management.php?action=view&id=<?php echo $msg['id']; ?>'">
                <div class="row align-items-center">
                    <div class="col-md-1">
                        <i class="fas fa-envelope fa-2x" style="color: #667eea;"></i>
                    </div>
                    <div class="col-md-7">
                        <h6 class="mb-1">
                            <?php echo htmlspecialchars($msg['subject']); ?>
                            <span class="badge badge-status badge-<?php echo $msg['status']; ?>">
                                <?php echo ucfirst($msg['status']); ?>
                            </span>
                        </h6>
                        <small class="text-muted">
                            From: <strong><?php echo htmlspecialchars($msg['name']); ?></strong> 
                            (<?php echo htmlspecialchars($msg['email']); ?>)
                        </small><br>
                        <small class="text-dark">
                            <?php echo htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : ''); ?>
                        </small>
                    </div>
                    <div class="col-md-2 text-end">
                        <span class="badge badge-priority-<?php echo $msg['priority']; ?>">
                            <?php echo ucfirst($msg['priority']); ?> Priority
                        </span><br>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($msg['category']); ?>
                        </small>
                    </div>
                    <div class="col-md-2 text-end">
                        <small class="text-muted d-block">
                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <p class="text-muted text-center py-4">No messages found.</p>
        <?php endif; ?>
    </div>
    
    <?php else: // Single message view
    if ($message): ?>
    
    <a href="contact-management.php" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left"></i> Back to Messages
    </a>
    
    <div class="admin-card">
        <div class="row mb-3">
            <div class="col-md-8">
                <h5><?php echo htmlspecialchars($message['subject']); ?></h5>
                <small class="text-muted">
                    From: <strong><?php echo htmlspecialchars($message['name']); ?></strong><br>
                    Email: <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>"><?php echo htmlspecialchars($message['email']); ?></a><br>
                    <?php if ($message['phone']): ?>
                    Phone: <a href="tel:<?php echo htmlspecialchars($message['phone']); ?>"><?php echo htmlspecialchars($message['phone']); ?></a><br>
                    <?php endif; ?>
                    Submitted: <?php echo date('F d, Y H:i A', strtotime($message['created_at'])); ?>
                </small>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge badge-status badge-<?php echo $message['status']; ?>" style="font-size: 1rem;">
                    <?php echo ucfirst($message['status']); ?>
                </span><br>
                <span class="badge badge-priority-<?php echo $message['priority']; ?>" style="font-size: 0.9rem; margin-top: 5px;">
                    <?php echo ucfirst($message['priority']); ?> Priority
                </span><br>
                <small class="text-muted d-block mt-2">
                    Category: <strong><?php echo htmlspecialchars($message['category']); ?></strong>
                </small>
            </div>
        </div>
        
        <hr>
        
        <div class="mb-4">
            <h6>Customer Message:</h6>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
            </div>
        </div>
        
        <!-- Admin Response Form -->
        <form method="POST" action="contact-management.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="<?php echo $message['id']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Update Status</label>
                    <select name="status" class="form-control">
                        <option value="new" <?php echo $message['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="read" <?php echo $message['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="responded" <?php echo $message['status'] === 'responded' ? 'selected' : ''; ?>>Responded</option>
                        <option value="closed" <?php echo $message['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Assign To (Admin)</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Current Admin'); ?>" disabled>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Response Message</label>
                <textarea name="response" class="form-control" rows="5" placeholder="Type your response to send to the customer..."></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Internal Admin Notes</label>
                <textarea name="admin_notes" class="form-control" rows="3" placeholder="Private notes visible only to admins..."><?php echo htmlspecialchars($message['admin_notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="btn-group" role="group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save & Update
                </button>
            </div>
        </form>
        
        <hr>
        
        <!-- Delete Option -->
        <form method="POST" action="contact-management.php" onsubmit="return confirm('Delete this message?');" style="display: inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo $message['id']; ?>">
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="fas fa-trash"></i> Delete Message
            </button>
        </form>
    </div>
    
    <?php else: ?>
    <div class="alert alert-danger">Message not found.</div>
    <?php endif; ?>
    
    <?php endif; ?>
    
</div>

</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include __DIR__ . '/../includes/footer/footer.php'; ?>
