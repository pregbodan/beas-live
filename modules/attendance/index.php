<?php
$pageTitle = 'Attendance Log';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$courseId = $_GET['courseId'] ?? '';
$date     = $_GET['date']     ?? date('Y-m-d');
$status   = $_GET['status']   ?? '';
$search   = sanitize($_GET['search'] ?? '');
$perPage  = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page-1) * $perPage;

$where  = ["a.attendanceDate=?"];
$params = [$date];
if ($courseId) { $where[] = "a.courseId=?"; $params[] = $courseId; }
if ($status)   { $where[] = "a.signInStatus=?"; $params[] = $status; }
if ($search)   { $where[] = "(s.matricNumber LIKE ? OR s.firstName LIKE ? OR s.surname LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM attendance a JOIN students s ON a.studentId=s.id WHERE $whereStr");
$total->execute($params);
$totalRows  = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("
    SELECT a.*, s.firstName, s.surname, s.matricNumber as sMatric, s.level,
           c.courseCode, c.courseTitle
    FROM attendance a
    JOIN students s ON a.studentId=s.id
    JOIN courses  c ON a.courseId=c.id
    WHERE $whereStr
    ORDER BY a.signInTime DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$records = $stmt->fetchAll();

$courses = $db->query("SELECT id, courseCode, courseTitle FROM courses WHERE isActive=1 ORDER BY courseCode")->fetchAll();

// Summary for selected date/course
$summary = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(signInStatus='present') as present,
        SUM(signInStatus='rejected') as rejected
    FROM attendance a WHERE $whereStr
");
$summary->execute($params);
$summary = $summary->fetch();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Attendance Log</h1>
        <p class="page-subtitle">Biometric verification records</p>
    </div>
    <div class="btn-group">
        <a href="<?= APP_URL ?>/modules/reports/index.php" class="btn btn--ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Reports
        </a>
        <a href="<?= APP_URL ?>/api/export.php?date=<?= urlencode($date) ?>&courseId=<?= urlencode($courseId) ?>" class="btn btn--primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
    </div>
</div>

<!-- Stats Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="stat-card stat-card--electric">
        <div class="stat-value"><?= number_format($summary['total'] ?? 0) ?></div>
        <div class="stat-label">Total Attempts</div>
    </div>
    <div class="stat-card stat-card--signal">
        <div class="stat-value"><?= number_format($summary['present'] ?? 0) ?></div>
        <div class="stat-label">Verified Present</div>
    </div>
    <div class="stat-card stat-card--danger">
        <div class="stat-value"><?= number_format($summary['rejected'] ?? 0) ?></div>
        <div class="stat-label">Rejected / Failed</div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:12px">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;flex:1">
            <input type="date" name="date" class="form-control" style="width:160px" value="<?= $date ?>">
            <select name="courseId" class="form-control" style="width:200px">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id']==$courseId?'selected':'' ?>><?= htmlspecialchars($c['courseCode']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="width:140px">
                <option value="">All Status</option>
                <option value="present" <?= $status==='present'?'selected':'' ?>>Present</option>
                <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
            </select>
            <div class="search-input-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" class="form-control" placeholder="Name or matric…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn--ghost">Filter</button>
        </form>
    </div>

    <div class="table-wrap card-body--flush">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Matric No.</th>
                    <th>Course</th>
                    <th>Sign-In</th>
                    <th>Sign-Out</th>
                    <th>Duration</th>
                    <th>Method</th>
                    <th>Finger</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr><td colspan="9">
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                        <div class="empty-state-title">No records for this date</div>
                        <div class="empty-state-sub">Try selecting a different date or run a verification session</div>
                    </div>
                </td></tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="width:30px;height:30px;border-radius:50%;background:var(--bg-hover);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;color:var(--electric);flex-shrink:0">
                                    <?= strtoupper(substr($r['firstName'],0,1).substr($r['surname'],0,1)) ?>
                                </div>
                                <span class="td-primary"><?= htmlspecialchars($r['surname'].', '.$r['firstName']) ?></span>
                            </div>
                        </td>
                        <td class="td-mono"><?= htmlspecialchars($r['sMatric']) ?></td>
                        <td><span class="td-mono"><?= htmlspecialchars($r['courseCode']) ?></span></td>
                        <td><?= $r['signInTime'] ? date('H:i:s', strtotime($r['signInTime'])) : '—' ?></td>
                        <td><?= $r['signOutTime'] ? date('H:i:s', strtotime($r['signOutTime'])) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td><?= htmlspecialchars($r['totalDuration'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge--<?= $r['verificationMethod']==='fingerprint'?'blue':'amber' ?>">
                                <?= ucfirst($r['verificationMethod'] ?? 'fingerprint') ?>
                            </span>
                        </td>
                        <td style="text-transform:capitalize"><?= htmlspecialchars($r['signInFingerUsed'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge--<?= $r['signInStatus']==='present'?'success':'danger' ?>">
                                <?= ucfirst($r['signInStatus']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&date=<?= urlencode($date) ?>&courseId=<?= urlencode($courseId) ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"
           class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

