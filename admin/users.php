<?php
require '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Manage Users';
$error = '';
$success = '';

// Determine if `status` column exists (graceful fallback)
$hasStatusCol = false;
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($colCheck && $colCheck->num_rows > 0) { $hasStatusCol = true; }

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action  = $_POST['action']  ?? '';
        $userId  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        // Safety: never let an admin act on themselves or on another admin
        if ($userId > 0) {
            $check = $conn->prepare("SELECT id, fullname, user_type FROM users WHERE id = ?");
            $check->bind_param("i", $userId);
            $check->execute();
            $target = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$target) {
                $error = 'User not found.';
            } elseif ($target['user_type'] === 'admin') {
                $error = 'You cannot modify another admin from this page.';
            } elseif ((int)$target['id'] === (int)$_SESSION['user_id']) {
                $error = 'You cannot modify your own account here.';
            } else {
                switch ($action) {
                    case 'edit':
                        $fullname  = trim($_POST['fullname']  ?? '');
                        $email     = trim($_POST['email']     ?? '');
                        $phone     = trim($_POST['phone']     ?? '');
                        $user_type = $_POST['user_type'] ?? 'regular';
                        $id_number = trim($_POST['id_number'] ?? '');
                        $allowedTypes = ['regular','pwd','senior'];
                        if (!in_array($user_type, $allowedTypes, true)) $user_type = 'regular';
                        // Set pwd_id / senior_id based on selected type; clear the other
                        $pwd_id    = ($user_type === 'pwd')    ? $id_number : null;
                        $senior_id = ($user_type === 'senior') ? $id_number : null;
                        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $error = 'Valid name and email are required.';
                        } elseif (in_array($user_type, ['pwd','senior'], true) && $id_number === '') {
                            $error = 'ID number is required for PWD/Senior accounts.';
                        } else {
                            // Email uniqueness
                            $dup = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                            $dup->bind_param("si", $email, $userId);
                            $dup->execute();
                            if ($dup->get_result()->num_rows > 0) {
                                $error = 'Another user already uses that email.';
                            } else {
                                $u = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, user_type = ?, pwd_id = ?, senior_id = ? WHERE id = ?");
                                $u->bind_param("ssssssi", $fullname, $email, $phone, $user_type, $pwd_id, $senior_id, $userId);
                                if ($u->execute()) {
                                    logAudit('user_updated', 'user', $userId, "Updated to type=$user_type");
                                    $success = 'User updated successfully.';
                                } else {
                                    $error = 'Failed to update user.';
                                }
                                $u->close();
                            }
                            $dup->close();
                        }
                        break;

                    case 'ban':
                    case 'unban':
                        if (!$hasStatusCol) {
                            $error = 'Ban feature unavailable: please apply migrate_system_fixes.sql.';
                        } else {
                            $newStatus = ($action === 'ban') ? 'banned' : 'active';
                            $u = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                            $u->bind_param("si", $newStatus, $userId);
                            if ($u->execute()) {
                                // Invalidate any remember-me tokens on ban
                                if ($newStatus === 'banned') {
                                    $rt = $conn->query("SHOW TABLES LIKE 'remember_tokens'");
                                    if ($rt && $rt->num_rows > 0) {
                                        $del = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                                        $del->bind_param("i", $userId);
                                        $del->execute();
                                        $del->close();
                                    }
                                }
                                logAudit('user_' . $action . 'ned', 'user', $userId, "Status set to $newStatus");
                                $success = ($newStatus === 'banned') ? 'User has been banned.' : 'User has been unbanned.';
                            } else {
                                $error = 'Failed to update user status.';
                            }
                            $u->close();
                        }
                        break;

                    case 'delete':
                        $u = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $u->bind_param("i", $userId);
                        if ($u->execute()) {
                            logAudit('user_deleted', 'user', $userId, "Deleted user: " . $target['fullname']);
                            $success = 'User deleted successfully.';
                        } else {
                            $error = 'Failed to delete user. They may have related records (orders, etc.).';
                        }
                        $u->close();
                        break;

                    default:
                        $error = 'Unknown action.';
                }
            }
        }
    }
}

// Get all users (excluding admins)
$selectCols = $hasStatusCol
    ? "id, fullname, email, phone, user_type, pwd_id, senior_id, created_at, status"
    : "id, fullname, email, phone, user_type, pwd_id, senior_id, created_at";
$users = $conn->query("SELECT $selectCols FROM users WHERE user_type != 'admin' ORDER BY created_at DESC");
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-users"></i> Manage Users
        </h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (!$hasStatusCol): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> The <code>users.status</code> column is missing. Run <code>migrate_system_fixes.sql</code> to enable banning users.
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <h5 class="text-coffee-dark mb-4" style="font-weight: 700;">
            <i class="fas fa-list"></i> Customer Accounts
        </h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>ID Number</th>
                        <?php if ($hasStatusCol): ?><th>Status</th><?php endif; ?>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <?php
                        $isBanned = $hasStatusCol && ($user['status'] ?? 'active') === 'banned';
                        $idNum = '-';
                        if ($user['user_type'] === 'pwd' && !empty($user['pwd_id']))    $idNum = $user['pwd_id'];
                        elseif ($user['user_type'] === 'senior' && !empty($user['senior_id'])) $idNum = $user['senior_id'];
                    ?>
                    <tr>
                        <td><?php echo (int)$user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['user_type'] === 'pwd' ? 'info' : ($user['user_type'] === 'senior' ? 'warning' : 'secondary'); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $user['user_type'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($idNum); ?></td>
                        <?php if ($hasStatusCol): ?>
                        <td>
                            <?php if ($isBanned): ?>
                                <span class="badge bg-danger">Banned</span>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td class="text-end">
                            <div class="ad-actions">
                            <button type="button" class="ad-act" title="Edit user"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editUserModal"
                                    data-id="<?php echo (int)$user['id']; ?>"
                                    data-fullname="<?php echo htmlspecialchars($user['fullname'], ENT_QUOTES); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES); ?>"
                                    data-type="<?php echo htmlspecialchars($user['user_type'], ENT_QUOTES); ?>"
                                    data-pwd-id="<?php echo htmlspecialchars($user['pwd_id'] ?? '', ENT_QUOTES); ?>"
                                    data-senior-id="<?php echo htmlspecialchars($user['senior_id'] ?? '', ENT_QUOTES); ?>">
                                <?php echo adminIcon('edit'); ?><span class="ad-act-text">Edit</span>
                            </button>
                            <?php if ($hasStatusCol): ?>
                                <form method="POST" onsubmit="return confirm('<?php echo $isBanned ? 'Unban' : 'Ban'; ?> this user?');">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                    <input type="hidden" name="action" value="<?php echo $isBanned ? 'unban' : 'ban'; ?>">
                                    <button type="submit" class="ad-act <?php echo $isBanned ? 'ad-act-ok' : 'ad-act-warn'; ?>" title="<?php echo $isBanned ? 'Unban user' : 'Ban user'; ?>">
                                        <?php echo adminIcon($isBanned ? 'unlock' : 'ban'); ?>
                                        <span class="ad-act-text"><?php echo $isBanned ? 'Unban' : 'Ban'; ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Permanently delete this user? This cannot be undone.');">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="ad-act ad-act-danger" title="Delete user">
                                    <?php echo adminIcon('trash'); ?><span class="ad-act-text">Delete</span>
                                </button>
                            </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="fullname" id="edit_fullname" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" id="edit_email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" id="edit_phone">
                </div>
                <div class="mb-3">
                    <label class="form-label">Account Type</label>
                    <select class="form-select" name="user_type" id="edit_user_type">
                        <option value="regular">Regular</option>
                        <option value="pwd">PWD</option>
                        <option value="senior">Senior</option>
                    </select>
                </div>
                <div class="mb-3" id="edit_id_number_group" style="display:none;">
                    <label class="form-label" id="edit_id_number_label">ID Number</label>
                    <input type="text" class="form-control" name="id_number" id="edit_id_number" placeholder="Enter PWD/Senior ID number">
                    <small class="text-muted">Required for PWD and Senior accounts.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('editUserModal');
    if (!modal) return;
    var typeSelect = document.getElementById('edit_user_type');
    var idGroup    = document.getElementById('edit_id_number_group');
    var idLabel    = document.getElementById('edit_id_number_label');
    var idInput    = document.getElementById('edit_id_number');
    var pwdIdCache = '';
    var seniorIdCache = '';

    function syncIdField() {
        var t = typeSelect.value;
        if (t === 'pwd') {
            idGroup.style.display = '';
            idLabel.textContent = 'PWD ID Number';
            idInput.value = pwdIdCache;
            idInput.required = true;
        } else if (t === 'senior') {
            idGroup.style.display = '';
            idLabel.textContent = 'Senior Citizen ID Number';
            idInput.value = seniorIdCache;
            idInput.required = true;
        } else {
            idGroup.style.display = 'none';
            idInput.value = '';
            idInput.required = false;
        }
    }

    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        document.getElementById('edit_user_id').value   = btn.getAttribute('data-id');
        document.getElementById('edit_fullname').value  = btn.getAttribute('data-fullname');
        document.getElementById('edit_email').value     = btn.getAttribute('data-email');
        document.getElementById('edit_phone').value     = btn.getAttribute('data-phone');
        typeSelect.value = btn.getAttribute('data-type');
        pwdIdCache    = btn.getAttribute('data-pwd-id') || '';
        seniorIdCache = btn.getAttribute('data-senior-id') || '';
        syncIdField();
    });

    typeSelect.addEventListener('change', syncIdField);
});
</script>

    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
