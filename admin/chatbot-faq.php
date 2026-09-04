<?php
require_once __DIR__ . '/../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'Chatbot FAQ Management';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $category = trim($_POST['category']);
        $priority = intval($_POST['priority']);
        $userId = $_SESSION['user_id'];
        
        if (!empty($question) && !empty($answer)) {
            $stmt = $conn->prepare("INSERT INTO chatbot_faq (question, answer, category, priority, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $question, $answer, $category, $priority, $userId);
            
            if ($stmt->execute()) {
                $successMsg = "✅ FAQ added successfully!";
            } else {
                $errorMsg = "❌ Error adding FAQ: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errorMsg = "❌ Question and Answer are required!";
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $category = trim($_POST['category']);
        $priority = intval($_POST['priority']);
        $active = intval($_POST['active'] ?? 0);
        
        if (!empty($question) && !empty($answer)) {
            $stmt = $conn->prepare("UPDATE chatbot_faq SET question=?, answer=?, category=?, priority=?, active=? WHERE id=?");
            $stmt->bind_param("sssiii", $question, $answer, $category, $priority, $active, $id);
            
            if ($stmt->execute()) {
                $successMsg = "✅ FAQ updated successfully!";
            } else {
                $errorMsg = "❌ Error updating FAQ: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errorMsg = "❌ Question and Answer are required!";
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM chatbot_faq WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $successMsg = "✅ FAQ deleted successfully!";
        } else {
            $errorMsg = "❌ Error deleting FAQ: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all FAQs
$faqResult = $conn->query("SELECT * FROM chatbot_faq ORDER BY priority DESC, created_at DESC");
$faqs = $faqResult->fetch_all(MYSQLI_ASSOC);

// Get categories
$categories = $conn->query("SELECT DISTINCT category FROM chatbot_faq ORDER BY category")->fetch_all(MYSQLI_ASSOC);
?>

<?php include __DIR__ . '/../includes/header/header.php'; ?>
<?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

<style>
    .faq-item {
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 4px solid var(--primary);
        transition: all 0.3s ease;
    }
    .faq-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .faq-question { font-weight: 600; color: var(--text-dark); margin-bottom: 8px; }
    .faq-answer { color: #666; font-size: 0.95rem; white-space: pre-wrap; margin-bottom: 10px; max-height: 100px; overflow-y: auto; }
    .faq-meta { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #999; }
    .badge-category { display: inline-block; padding: 4px 10px; background: #f0f0f0; border-radius: 20px; font-size: 0.85rem; color: #666; }
    .btn-action { padding: 5px 10px; font-size: 0.85rem; margin: 0 2px; }
    textarea { font-family: 'Courier New', monospace; }
</style>

<div class="admin-container">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-robot"></i> Chatbot FAQ Management
        </h1>
        <p class="text-muted">Manage and customize AI chatbot responses</p>
    </div>

    <!-- Messages -->
    <?php if (isset($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Add New FAQ Button -->
    <div class="mb-4">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFaqModal">
            <i class="fas fa-plus"></i> Add New FAQ
        </button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="admin-card text-center">
                <h5 class="mb-2"><?php echo count($faqs); ?></h5>
                <p class="mb-0 text-muted">Total FAQs</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card text-center">
                <h5 class="mb-2"><?php echo count($categories); ?></h5>
                <p class="mb-0 text-muted">Categories</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card text-center">
                <h5 class="mb-2"><?php echo count(array_filter($faqs, fn($f) => $f['active'] == 1)); ?></h5>
                <p class="mb-0 text-muted">Active FAQs</p>
            </div>
        </div>
    </div>
    
    <!-- FAQs List -->
    <div class="admin-card">
        <h5><i class="fas fa-list"></i> FAQ Database</h5>
        
        <?php if (count($faqs) > 0): ?>
        <div id="faqList">
            <?php foreach ($faqs as $faq): ?>
            <div class="faq-item">
                <div class="faq-question">
                    <i class="fas fa-lightbulb"></i> 
                    <?php echo htmlspecialchars($faq['question']); ?>
                    <?php if (!$faq['active']): ?>
                    <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </div>
                <div class="faq-answer">
                    <?php echo htmlspecialchars($faq['answer']); ?>
                </div>
                <div class="faq-meta">
                    <div>
                        <span class="badge-category">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($faq['category']); ?>
                        </span>
                        <span class="ms-2">Priority: <strong><?php echo $faq['priority']; ?></strong></span>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary btn-action" onclick="editFaq(<?php echo $faq['id']; ?>, '<?php echo htmlspecialchars(json_encode($faq)); ?>')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this FAQ?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $faq['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted text-center py-4">No FAQs found. Add one to get started!</p>
        <?php endif; ?>
    </div>
    
</div>

<!-- Add/Edit FAQ Modal -->
<div class="modal fade" id="addFaqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New FAQ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="faqId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Question / Keywords *</label>
                        <input type="text" class="form-control" name="question" id="faqQuestion" placeholder="e.g., 'shipping delivery how long'" required>
                        <small class="text-muted">Use keywords separated by spaces that users might type</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Answer *</label>
                        <textarea class="form-control" name="answer" id="faqAnswer" rows="6" placeholder="Bot response (supports emojis and line breaks)" required></textarea>
                        <small class="text-muted">Tip: Use \n for line breaks and emojis for engagement</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-control" name="category" id="faqCategory">
                                    <option value="general">General</option>
                                    <option value="products">Products</option>
                                    <option value="pricing">Pricing</option>
                                    <option value="shipping">Shipping</option>
                                    <option value="payment">Payment</option>
                                    <option value="orders">Orders</option>
                                    <option value="support">Support</option>
                                    <option value="account">Account</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Priority (Higher = Matched First)</label>
                                <input type="number" class="form-control" name="priority" id="faqPriority" value="50" min="1" max="999">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="active" id="faqActive" value="1" checked>
                        <label class="form-check-label" for="faqActive">
                            Active (Bot will use this FAQ)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editFaq(id, faqJson) {
    const faq = JSON.parse(faqJson);
    
    document.getElementById('modalTitle').textContent = 'Edit FAQ';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('faqId').value = faq.id;
    document.getElementById('faqQuestion').value = faq.question;
    document.getElementById('faqAnswer').value = faq.answer;
    document.getElementById('faqCategory').value = faq.category;
    document.getElementById('faqPriority').value = faq.priority;
    document.getElementById('faqActive').checked = faq.active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('addFaqModal'));
    modal.show();
}

// Reset form when modal is closed
document.getElementById('addFaqModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modalTitle').textContent = 'Add New FAQ';
    document.getElementById('formAction').value = 'add';
    document.getElementById('faqId').value = '';
    document.querySelector('form').reset();
    document.getElementById('faqPriority').value = '50';
    document.getElementById('faqActive').checked = true;
});
</script>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include __DIR__ . '/../includes/footer/footer.php'; ?>
