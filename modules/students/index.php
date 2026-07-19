<?php
$pageTitle = 'Students';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$search  = sanitize($_GET['search'] ?? '');
$level   = $_GET['level']   ?? '';
$dept    = $_GET['dept']    ?? '';
$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = ["s.isActive=1"];
$params = [];
if ($search) {
    $where[] = "(s.matricNumber LIKE ? OR s.firstName LIKE ? OR s.surname LIKE ? OR s.email LIKE ?)";
    $params  = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($level) { $where[] = "s.level=?"; $params[] = $level; }

$whereStr = implode(' AND ', $where);
$total    = $db->prepare("SELECT COUNT(*) FROM students s WHERE $whereStr");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("SELECT s.*, (SELECT COUNT(*) FROM course_registrations cr WHERE cr.studentId=s.id AND cr.isActive=1) as course_count
    FROM students s WHERE $whereStr ORDER BY s.enrolled_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Students</h1>
        <p class="page-subtitle"><?= number_format($totalRows) ?> enrolled students</p>
    </div>
    <div class="btn-group">
        <a href="<?= APP_URL ?>/modules/students/enroll.php" class="btn btn--primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Enroll Student
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:12px">
        <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap;align-items:center">
            <div class="search-input-wrap" style="min-width:220px">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" class="form-control" placeholder="Search by name or matric…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="level" class="form-control" style="width:140px">
                <option value="">All Levels</option>
                <option value="100" <?= $level=='100'?'selected':'' ?>>100 Level</option>
                <option value="200" <?= $level=='200'?'selected':'' ?>>200 Level</option>
                <option value="300" <?= $level=='300'?'selected':'' ?>>300 Level</option>
                <option value="400" <?= $level=='400'?'selected':'' ?>>400 Level</option>
                <option value="500" <?= $level=='500'?'selected':'' ?>>500 Level</option>
            </select>
            <button type="submit" class="btn btn--ghost">Filter</button>
            <?php if ($search || $level): ?>
                <a href="?" class="btn btn--ghost">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrap card-body--flush">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Matric No.</th>
                    <th>Level</th>
                    <th>Biometrics</th>
                    <th>Courses</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <div class="empty-state-title">No students found</div>
                        <div class="empty-state-sub">Try adjusting your search filters</div>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($students as $s): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--bg-hover);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:var(--electric);flex-shrink:0">
                                <?= strtoupper(substr($s['firstName'],0,1).substr($s['surname'],0,1)) ?>
                            </div>
                            <div>
                                <div class="td-primary"><?= htmlspecialchars($s['surname'] . ', ' . $s['firstName']) ?></div>
                                <div style="font-size:.73rem;color:var(--text-muted)"><?= htmlspecialchars($s['email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="td-mono"><?= htmlspecialchars($s['matricNumber']) ?></td>
                    <td><span class="badge badge--blue"><?= htmlspecialchars($s['level']) ?> Level</span></td>
                    <td>
                        <?php if ($s['fingerprintsCaptured']): ?>
                            <span class="badge badge--success">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><polyline points="20 6 9 17 4 12"/></svg>
                                Captured
                            </span>
                        <?php else: ?>
                            <span class="badge badge--danger">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="text-mono text-electric"><?= $s['course_count'] ?></span></td>
                    <td>
                        <?php if ($s['isActive']): ?>
                            <span class="badge badge--success">Active</span>
                        <?php else: ?>
                            <span class="badge badge--muted">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= APP_URL ?>/modules/students/view.php?id=<?= $s['id'] ?>" class="btn btn--ghost btn--sm">View</a>
                            <a href="<?= APP_URL ?>/modules/students/enroll.php?id=<?= $s['id'] ?>" class="btn btn--ghost btn--sm">Edit</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&level=<?= urlencode($level) ?>"
               class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
