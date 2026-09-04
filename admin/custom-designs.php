<?php
require '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Custom Design Requests';
$error = '';
$success = '';

// Run migration if table doesn't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'custom_designs'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/../migrate_custom_designs.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['design_id'])) {
    $designId = intval($_POST['design_id']);
    $status = $conn->real_escape_string(trim($_POST['status'] ?? ''));
    $adminNotes = $conn->real_escape_string(trim($_POST['admin_notes'] ?? ''));

    $allowedStatuses = ['pending', 'approved', 'revision', 'completed', 'cancelled'];
    if (in_array($status, $allowedStatuses)) {
        $stmt = $conn->prepare("UPDATE custom_designs SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $adminNotes, $designId);
        if ($stmt->execute()) {
            $success = 'Design status updated successfully!';
        } else {
            $error = 'Failed to update design status.';
        }
        $stmt->close();
    } else {
        $error = 'Invalid status value.';
    }
}

// Get filter
$statusFilter = $_GET['status'] ?? '';
$whereClause = '';
if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'revision', 'completed', 'cancelled'])) {
    $whereClause = "WHERE cd.status = '" . $conn->real_escape_string($statusFilter) . "'";
}

// Get all custom designs with user info
$designs = $conn->query("
    SELECT cd.*, u.fullname, u.email 
    FROM custom_designs cd 
    JOIN users u ON cd.user_id = u.id 
    $whereClause
    ORDER BY cd.created_at DESC
");

// Stats
$totalDesigns = $conn->query("SELECT COUNT(*) as c FROM custom_designs")->fetch_assoc()['c'];
$pendingDesigns = $conn->query("SELECT COUNT(*) as c FROM custom_designs WHERE status = 'pending'")->fetch_assoc()['c'];
$approvedDesigns = $conn->query("SELECT COUNT(*) as c FROM custom_designs WHERE status = 'approved'")->fetch_assoc()['c'];
?>

<?php include '../includes/header/header.php'; ?>

<style>
.design-stats-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.design-stat-card {
    flex: 1;
    min-width: 140px;
    background: #fff;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid #eee;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.design-stat-card .stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--text-dark, #1a1a1a);
}

.design-stat-card .stat-text {
    font-size: 0.8rem;
    color: var(--text-light, #888);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.4rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 600;
    border: 1px solid #e0e0e0;
    color: #666;
    transition: all 0.2s;
}

.filter-tab:hover {
    border-color: #aaa;
    color: #333;
}

.filter-tab.active {
    background: var(--accent-green, #2d6a4f);
    color: #fff;
    border-color: var(--accent-green, #2d6a4f);
}

.design-request-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #eee;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    margin-bottom: 1rem;
    overflow: hidden;
    transition: all 0.2s;
}

.design-request-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.design-request-row {
    display: grid;
    grid-template-columns: 140px 1fr 160px 200px;
    gap: 1rem;
    align-items: center;
    padding: 1rem 1.25rem;
}

.design-thumb {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 10px;
    background: #f8f9fa;
    border: 1px solid #eee;
    cursor: pointer;
    transition: transform 0.2s;
}

.design-thumb:hover {
    transform: scale(1.05);
}

.design-info h6 {
    font-weight: 700;
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
}

.design-info p {
    font-size: 0.82rem;
    color: #888;
    margin-bottom: 0.15rem;
}

.design-badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.design-badge.pending { background: #fff3cd; color: #856404; }
.design-badge.approved { background: #d4edda; color: #155724; }
.design-badge.revision { background: #f8d7da; color: #721c24; }
.design-badge.completed { background: #d1ecf1; color: #0c5460; }
.design-badge.cancelled { background: #e2e3e5; color: #383d41; }

.design-actions-form select {
    font-size: 0.82rem;
    padding: 0.35rem 0.5rem;
    border-radius: 8px;
    border: 1px solid #ddd;
    margin-bottom: 0.4rem;
}

.design-actions-form textarea {
    font-size: 0.8rem;
    padding: 0.35rem 0.5rem;
    border-radius: 8px;
    border: 1px solid #ddd;
    resize: vertical;
    min-height: 40px;
    width: 100%;
    margin-bottom: 0.4rem;
}

.design-actions-form .btn-sm {
    font-size: 0.78rem;
    padding: 0.3rem 0.7rem;
    border-radius: 8px;
}

/* Modal for design preview */
.design-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.design-modal-overlay.show {
    display: flex;
}

.design-modal-content {
    background: #fff;
    border-radius: 16px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 1.5rem;
    position: relative;
}

.design-modal-close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    border: none;
    background: #f0f0f0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.design-modal-img {
    width: 100%;
    border-radius: 10px;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .design-request-row {
        grid-template-columns: 1fr;
    }
    .design-thumb {
        width: 100%;
        height: 200px;
    }
}
</style>

<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-palette"></i> Custom Design Requests
        </h1>
        <p class="text-muted">Review and manage customer custom apparel design submissions</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="design-stats-row">
        <div class="design-stat-card">
            <div class="stat-number"><?php echo $totalDesigns; ?></div>
            <div class="stat-text">Total Designs</div>
        </div>
        <div class="design-stat-card">
            <div class="stat-number" style="color: #e67e22;"><?php echo $pendingDesigns; ?></div>
            <div class="stat-text">Pending Review</div>
        </div>
        <div class="design-stat-card">
            <div class="stat-number" style="color: #27ae60;"><?php echo $approvedDesigns; ?></div>
            <div class="stat-text">Approved</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="custom-designs.php" class="filter-tab <?php echo !$statusFilter ? 'active' : ''; ?>">All</a>
        <a href="?status=pending" class="filter-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="?status=approved" class="filter-tab <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>">Approved</a>
        <a href="?status=revision" class="filter-tab <?php echo $statusFilter === 'revision' ? 'active' : ''; ?>">Revision</a>
        <a href="?status=completed" class="filter-tab <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">Completed</a>
        <a href="?status=cancelled" class="filter-tab <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
    </div>

    <!-- Design Requests List -->
    <?php if ($designs && $designs->num_rows > 0): ?>
        <?php while ($design = $designs->fetch_assoc()): ?>
            <div class="design-request-card">
                <div class="design-request-row">
                    <!-- Thumbnail -->
                    <div>
                        <img src="../<?php echo htmlspecialchars($design['design_image']); ?>" 
                             class="design-thumb" 
                             alt="Design Preview"
                             onclick="showDesignModal(<?php echo (int)$design['id']; ?>)"
                             onerror="this.src='https://placehold.co/120x120/f0f0f0/999?text=Design'">
                    </div>

                    <!-- Info -->
                    <div class="design-info">
                        <h6>
                            <?php echo ucfirst(htmlspecialchars($design['product_type'])); ?> Design
                            <span class="design-badge <?php echo htmlspecialchars($design['status']); ?>"><?php echo htmlspecialchars($design['status']); ?></span>
                        </h6>
                        <p><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($design['fullname']); ?> (<?php echo htmlspecialchars($design['email']); ?>)</p>
                        <p><i class="fas fa-calendar me-1"></i> <?php echo date('M d, Y h:i A', strtotime($design['created_at'])); ?></p>
                        <?php if ($design['notes']): ?>
                            <p><i class="fas fa-sticky-note me-1"></i> <?php echo htmlspecialchars($design['notes']); ?></p>
                        <?php endif; ?>
                        <?php if ($design['order_id']): ?>
                            <p><i class="fas fa-receipt me-1"></i> Linked to Order #<?php echo (int)$design['order_id']; ?>
                                <a href="order_details.php?id=<?php echo (int)$design['order_id']; ?>" class="text-decoration-none small ms-1">View Order</a>
                            </p>
                        <?php endif; ?>
                        <?php if ($design['admin_notes']): ?>
                            <p style="color:#2d6a4f;"><i class="fas fa-comment me-1"></i> <strong>Admin:</strong> <?php echo htmlspecialchars($design['admin_notes']); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Download -->
                    <div class="text-center">
                        <a href="../<?php echo htmlspecialchars($design['design_image']); ?>" 
                           download="design_<?php echo (int)$design['id']; ?>_front.png" 
                           class="btn btn-sm btn-outline-primary mb-2" style="border-radius:8px;">
                            <i class="fas fa-download me-1"></i> Front
                        </a>
                        <?php if (!empty($design['design_image_back'])): ?>
                        <a href="../<?php echo htmlspecialchars($design['design_image_back']); ?>" 
                           download="design_<?php echo (int)$design['id']; ?>_back.png" 
                           class="btn btn-sm btn-outline-primary mb-2" style="border-radius:8px;">
                            <i class="fas fa-download me-1"></i> Back
                        </a>
                        <?php endif; ?>
                        <br>
                        <button class="btn btn-sm btn-outline-secondary" style="border-radius:8px;" 
                                onclick="showDesignModal(<?php echo (int)$design['id']; ?>)">
                            <i class="fas fa-eye me-1"></i> Preview
                        </button>
                    </div>

                    <!-- Actions -->
                    <div>
                        <form method="POST" class="design-actions-form">
                            <input type="hidden" name="design_id" value="<?php echo (int)$design['id']; ?>">
                            <select name="status" class="form-select form-select-sm">
                                <option value="pending" <?php echo $design['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $design['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="revision" <?php echo $design['status'] === 'revision' ? 'selected' : ''; ?>>Request Revision</option>
                                <option value="completed" <?php echo $design['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $design['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <textarea name="admin_notes" placeholder="Admin notes..."><?php echo htmlspecialchars($design['admin_notes'] ?? ''); ?></textarea>
                            <button type="submit" class="btn btn-sm btn-primary w-100" style="border-radius:8px;">
                                <i class="fas fa-save me-1"></i> Update
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="admin-card text-center py-5">
            <i class="fas fa-palette" style="font-size:3rem; color:#ddd; margin-bottom:1rem; display:block;"></i>
            <h5 class="text-muted">No custom design requests yet</h5>
            <p class="text-muted">Customer design submissions will appear here.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Design Preview Modal -->
<div class="design-modal-overlay" id="designModal">
    <div class="design-modal-content">
        <button class="design-modal-close" onclick="closeDesignModal()">&times;</button>
        <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
            <div style="text-align:center;">
                <div style="font-weight:600; font-size:0.8rem; color:#888; margin-bottom:0.3rem;">FRONT</div>
                <img id="modalDesignImg" class="design-modal-img" src="" alt="Design Preview (Front)">
            </div>
            <div id="modalBackContainer" style="text-align:center; display:none;">
                <div style="font-weight:600; font-size:0.8rem; color:#888; margin-bottom:0.3rem;">BACK</div>
                <img id="modalDesignImgBack" class="design-modal-img" src="" alt="Design Preview (Back)">
            </div>
        </div>
        <div id="modalDesignInfo"></div>
    </div>
</div>

<script>
// Store design data for modal
const designsData = {};
<?php 
$designs->data_seek(0);
while ($d = $designs->fetch_assoc()): 
?>
designsData[<?php echo (int)$d['id']; ?>] = {
    image: '../<?php echo addslashes(htmlspecialchars($d['design_image'])); ?>',
    imageBack: '<?php echo addslashes(htmlspecialchars($d['design_image_back'] ?? '')); ?>',
    type: '<?php echo addslashes(htmlspecialchars($d['product_type'])); ?>',
    customer: '<?php echo addslashes(htmlspecialchars($d['fullname'])); ?>',
    email: '<?php echo addslashes(htmlspecialchars($d['email'])); ?>',
    notes: '<?php echo addslashes(htmlspecialchars($d['notes'] ?? '')); ?>',
    status: '<?php echo addslashes(htmlspecialchars($d['status'])); ?>',
    date: '<?php echo date('M d, Y h:i A', strtotime($d['created_at'])); ?>'
};
<?php endwhile; ?>

function showDesignModal(id) {
    const d = designsData[id];
    if (!d) return;
    
    document.getElementById('modalDesignImg').src = d.image;
    const backContainer = document.getElementById('modalBackContainer');
    if (d.imageBack) {
        document.getElementById('modalDesignImgBack').src = '../' + d.imageBack;
        backContainer.style.display = 'block';
    } else {
        backContainer.style.display = 'none';
    }
    document.getElementById('modalDesignInfo').innerHTML = `
        <h5 style="font-weight:700;">${d.type.charAt(0).toUpperCase() + d.type.slice(1)} Design</h5>
        <p><strong>Customer:</strong> ${d.customer} (${d.email})</p>
        <p><strong>Date:</strong> ${d.date}</p>
        <p><strong>Status:</strong> <span class="design-badge ${d.status}">${d.status}</span></p>
        ${d.notes ? `<p><strong>Notes:</strong> ${d.notes}</p>` : ''}
    `;
    document.getElementById('designModal').classList.add('show');
}

function closeDesignModal() {
    document.getElementById('designModal').classList.remove('show');
}

document.getElementById('designModal').addEventListener('click', function(e) {
    if (e.target === this) closeDesignModal();
});
</script>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
