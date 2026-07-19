<?php
$pageTitle = 'Courses';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO courses (courseCode,courseTitle,courseUnit,level,semester,createdBy) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            strtoupper(sanitize($_POST['courseCode'])),
            sanitize($_POST['courseTitle']),
            (int)$_POST['courseUnit'],
            (int)$_POST['level'],
            $_POST['semester'],
            currentUser()['id']
        ]);
        redirect($_SERVER['PHP_SELF'], 'Course added successfully');
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE courses SET isActive=NOT isActive WHERE id=?")->execute([(int)$_POST['id']]);
        redirect($_SERVER['PHP_SELF']);
    }
}

$level    = $_GET['level'] ?? '';
$semester = $_GET['semester'] ?? '';
$where    = []; $params = [];
if ($level)    { $where[] = "level=?"; $params[] = $level; }
if ($semester) { $where[] = "semester=?"; $params[] = $semester; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$courses = $db->prepare("SELECT c.*, u.username as createdByName,
    (SELECT COUNT(*) FROM course_registrations cr WHERE cr.courseId=c.id AND cr.isActive=1) as registered_count
    FROM courses c LEFT JOIN users u ON c.createdBy=u.id $whereStr ORDER BY c.level, c.semester, c.courseCode");
$courses->execute($params);
$courses = $courses->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Courses</h1>
        <p class="page-subtitle">Manage course catalogue and registrations</p>
    </div>
    <button class="btn btn--primary" onclick="document.getElementById('addModal').classList.add('open')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Course
    </button>
</div>

<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <form method="GET" style="display:flex;gap:10px;align-items:center">
            <select name="level" class="form-control" style="width:130px" onchange="this.form.submit()">
                <option value="">All Levels</option>
                <?php foreach ([100,200,300,400,500] as $l): ?>
                <option value="<?= $l ?>" <?= $level==$l?'selected':'' ?>><?= $l ?> Level</option>
                <?php endforeach; ?>
            </select>
            <select name="semester" class="form-control" style="width:140px" onchange="this.form.submit()">
                <option value="">All Semesters</option>
                <option value="first" <?= $semester==='first'?'selected':'' ?>>First Semester</option>
                <option value="second" <?= $semester==='second'?'selected':'' ?>>Second Semester</option>
            </select>
            <?php if ($level||$semester): ?><a href="?" class="btn btn--ghost btn--sm">Clear</a><?php endif; ?>
        </form>
        <span style="margin-left:auto;font-size:.8rem;color:var(--text-muted)"><?= count($courses) ?> courses</span>
    </div>

    <div class="table-wrap card-body--flush">
        <table>
            <thead>
                <tr>
                    <th>Code</th><th>Title</th><th>Units</th>
                    <th>Level</th><th>Semester</th><th>Registered</th>
                    <th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                <tr>
                    <td class="td-mono"><?= htmlspecialchars($c['courseCode']) ?></td>
                    <td class="td-primary"><?= htmlspecialchars($c['courseTitle']) ?></td>
                    <td style="text-align:center"><?= $c['courseUnit'] ?></td>
                    <td><span class="badge badge--blue"><?= $c['level'] ?></span></td>
                    <td><?= ucfirst($c['semester']) ?></td>
                    <td><span class="text-mono text-electric"><?= $c['registered_count'] ?></span></td>
                    <td>
                        <span class="badge <?= $c['isActive']?'badge--success':'badge--muted' ?>"><?= $c['isActive']?'Active':'Inactive' ?></span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= APP_URL ?>/modules/attendance/verify.php?courseId=<?= $c['id'] ?>" class="btn btn--ghost btn--sm">Verify</a>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn--ghost btn--sm"><?= $c['isActive']?'Deactivate':'Activate' ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add New Course</span>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Course Code <span>*</span></label>
                        <input type="text" name="courseCode" class="form-control text-mono" placeholder="CPE 501" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Units <span>*</span></label>
                        <select name="courseUnit" class="form-control" required>
                            <option value="1">1</option><option value="2">2</option>
                            <option value="3" selected>3</option><option value="6">6</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">Course Title <span>*</span></label>
                        <input type="text" name="courseTitle" class="form-control" placeholder="e.g. Digital Signal Processing" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Level <span>*</span></label>
                        <select name="level" class="form-control" required>
                            <?php foreach ([100,200,300,400,500] as $l): ?>
                            <option value="<?= $l ?>"><?= $l ?> Level</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Semester <span>*</span></label>
                        <select name="semester" class="form-control" required>
                            <option value="first">First</option>
                            <option value="second">Second</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn btn--primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
