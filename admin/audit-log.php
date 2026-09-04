<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Audit Log';

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'audit_log'");
$tableExists = ($tableCheck && $tableCheck->num_rows > 0);

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$filterAction = $_GET['action'] ?? '';
$filterUser = intval($_GET['user_id'] ?? 0);

$logs = [];
$totalLogs = 0;

if ($tableExists) {
    $where = "1=1";
    $params = [];
    $types = "";

    if (!empty($filterAction)) {
        $where .= " AND a.action LIKE ?";
        $params[] = "%$filterAction%";
        $types .= "s";
    }
    if ($filterUser > 0) {
        $where .= " AND a.user_id = ?";
        $params[] = $filterUser;
        $types .= "i";
    }

    // Count total
    $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM audit_log a WHERE $where");
    if (!empty($types)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalLogs = $countStmt->get_result()->fetch_assoc()['cnt'];
    $countStmt->close();

    // Get logs
    $stmt = $conn->prepare("SELECT a.*, u.fullname, u.email 
                            FROM audit_log a 
                            LEFT JOIN users u ON a.user_id = u.id 
                            WHERE $where 
                            ORDER BY a.created_at DESC 
                            LIMIT ? OFFSET ?");
    $params[] = $perPage;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$totalPages = ceil($totalLogs / $perPage);
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-clipboard-list"></i> Audit Log
        </h1>
        <p class="text-muted">Track all system actions and changes.</p>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">Audit log table not found. Run the migration first.</div>
    <?php else: ?>

    <!-- Filters -->
    <div class="admin-card mb-3">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
            <div>
                <label class="form-label small fw-bold">Action</label>
                <input type="text" name="action" class="form-control form-control-sm" placeholder="e.g. login, product" value="<?php echo htmlspecialchars($filterAction); ?>">
            </div>
            <div>
                <label class="form-label small fw-bold">User ID</label>
                <input type="number" name="user_id" class="form-control form-control-sm" placeholder="User ID" value="<?php echo $filterUser ?: ''; ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-dark">Filter</button>
                <a href="audit-log.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <p class="text-muted small mb-3"><?php echo number_format($totalLogs); ?> total entries</p>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No audit log entries found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="small"><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                            <td class="small">
                                <?php if ($log['fullname']): ?>
                                    <?php echo htmlspecialchars($log['fullname']); ?>
                                    <br><span class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($log['email']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo strpos($log['action'], 'delete') !== false ? 'danger' : 
                                        (strpos($log['action'], 'login') !== false ? 'info' : 
                                        (strpos($log['action'], 'add') !== false || strpos($log['action'], 'create') !== false ? 'success' : 'secondary')); 
                                ?>" style="font-size:0.72rem;">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="small">
                                <?php if ($log['entity_type']): ?>
                                    <?php echo htmlspecialchars($log['entity_type']); ?> #<?php echo $log['entity_id']; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="small" style="max-width:250px; overflow:hidden; text-overflow:ellipsis;">
                                <?php echo htmlspecialchars($log['details'] ?? ''); ?>
                            </td>
                            <td class="small text-muted"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&action=<?php echo urlencode($filterAction); ?>&user_id=<?php echo $filterUser; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer/footer.php'; ?>
