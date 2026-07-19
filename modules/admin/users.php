<?php
$pageTitle = 'Admin Users';
require_once __DIR__ . '/../../includes/header.php';
requireRole('superadmin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (username,email,password,full_name,role) VALUES (?,?,?,?,?)");
        $stmt->execute([
            sanitize($_POST['username']),
            sanitize($_POST['email']),
            $hash,
            sanitize($_POST['full_name']),
            $_POST['role'] ?? 'admin'
        ]);
        redirect(APP_URL . '/modules/admin/users.php', 'User created successfully');
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE users SET isActive=NOT isActive WHERE id=?")->execute([(int)$_POST['id']]);
        redirect(APP_URL . '/modules/admin/users.php');
    }
}

$users = $db->query("SELECT u.*, (SELECT COUNT(*) FROM attendance a WHERE a.signInTime > DATE_SUB(NOW(),INTERVAL 30 DAY)) as recent_activity FROM users u ORDER BY u.created_at DESC")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Admin Users</h1>
        <p class="page-subtitle">Manage system access and roles</p>
    </div>
    <button class="btn btn--primary" onclick="document.getElementById('addModal').classList.add('open')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add User
    </button>
</div>

<div class="card">
    <div class="table-wrap card-body--flush">
        <table>
            <thead>
                <tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#2D3A6E,#1E6FFF);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:#fff">
                                <?= strtoupper(substr($u['full_name']??$u['username'],0,2)) ?>
                            </div>
                            <div>
                                <div class="td-primary"><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></div>
                                <div style="font-size:.73rem;color:var(--text-muted)">@<?= htmlspecialchars($u['username']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php $roleColors = ['superadmin'=>'danger','admin'=>'blue','invigilator'=>'amber']; ?>
                        <span class="badge badge--<?= $roleColors[$u['role']] ?? 'muted' ?>"><?= ucfirst($u['role']) ?></span>
                    </td>
                    <td>
                        <span class="badge badge--<?= $u['isActive']?'success':'muted' ?>"><?= $u['isActive']?'Active':'Inactive' ?></span>
                    </td>
                    <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['id'] != currentUser()['id']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn--ghost btn--sm"><?= $u['isActive']?'Deactivate':'Activate' ?></button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:var(--text-muted)">Current user</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Admin User</span>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label class="form-label">Full Name <span>*</span></label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username <span>*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role <span>*</span></label>
                        <select name="role" class="form-control" required>
                            <option value="invigilator">Invigilator</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">Email <span>*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="user@fuoye.edu.ng">
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">Password <span>*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                        <span class="form-hint">Minimum 8 characters</span>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn btn--primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
