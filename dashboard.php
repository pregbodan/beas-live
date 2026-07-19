<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Stats
$totalStudents  = $db->query("SELECT COUNT(*) FROM students WHERE isActive=1")->fetchColumn();
$totalCourses   = $db->query("SELECT COUNT(*) FROM courses WHERE isActive=1")->fetchColumn();
$todayAttendance= $db->query("SELECT COUNT(*) FROM attendance WHERE attendanceDate=CURDATE() AND signInStatus='present'")->fetchColumn();
$pendingEnroll  = $db->query("SELECT COUNT(*) FROM students WHERE fingerprintsCaptured=0 AND isActive=1")->fetchColumn();

// Recent verifications
$recent = $db->query("
    SELECT a.*, s.firstName, s.surname, s.matricNumber, s.profilePictureUrl,
           c.courseCode, c.courseTitle
    FROM attendance a
    JOIN students s ON a.studentId = s.id
    JOIN courses c ON a.courseId = c.id
    ORDER BY a.signInTime DESC LIMIT 8
")->fetchAll();

// Today's sessions by course
$todayCourses = $db->query("
    SELECT c.courseCode, c.courseTitle,
           COUNT(a.id) as verified,
           (SELECT COUNT(*) FROM course_registrations cr WHERE cr.courseId=c.id AND cr.isActive=1) as expected
    FROM courses c
    LEFT JOIN attendance a ON a.courseId=c.id AND a.attendanceDate=CURDATE()
    WHERE c.isActive=1
    GROUP BY c.id
    HAVING verified > 0
    ORDER BY verified DESC LIMIT 5
")->fetchAll();

// Weekly trend (last 7 days)
$weekTrend = $db->query("
    SELECT DATE(signInTime) as day, COUNT(*) as total
    FROM attendance
    WHERE signInTime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND signInStatus='present'
    GROUP BY DATE(signInTime)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">System Overview</h1>
        <p class="page-subtitle"><?= date('l, d F Y') ?> — FUOYE Computer Engineering</p>
    </div>
    <div class="btn-group">
        <a href="<?= APP_URL ?>/modules/attendance/verify.php" class="btn btn--primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
            Start Verification
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card stat-card--electric">
        <div class="stat-icon stat-icon--electric">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-value"><?= number_format($totalStudents) ?></div>
        <div class="stat-label">Enrolled Students</div>
        <div class="stat-delta">↑ Active in system</div>
    </div>
    <div class="stat-card stat-card--signal">
        <div class="stat-icon stat-icon--signal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/></svg>
        </div>
        <div class="stat-value"><?= number_format($todayAttendance) ?></div>
        <div class="stat-label">Verified Today</div>
        <div class="stat-delta">Biometric confirmed</div>
    </div>
    <div class="stat-card stat-card--amber">
        <div class="stat-icon stat-icon--amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        </div>
        <div class="stat-value"><?= number_format($totalCourses) ?></div>
        <div class="stat-label">Active Courses</div>
        <div class="stat-delta">This semester</div>
    </div>
    <div class="stat-card stat-card--danger">
        <div class="stat-icon stat-icon--danger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-value"><?= number_format($pendingEnroll) ?></div>
        <div class="stat-label">Pending Biometrics</div>
        <div class="stat-delta">Fingerprint not captured</div>
    </div>
</div>

<div class="grid-2" style="gap:20px">
    <!-- Recent Verifications -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Verifications</span>
            <a href="<?= APP_URL ?>/modules/attendance/index.php" class="btn btn--ghost btn--sm">View All</a>
        </div>
        <div class="card-body--flush">
            <?php if (empty($recent)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                    <div class="empty-state-title">No verifications yet</div>
                    <div class="empty-state-sub">Start an exam session to see real-time attendance</div>
                </div>
            <?php else: ?>
                <?php foreach ($recent as $r): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border);">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--bg-hover);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--electric);flex-shrink:0">
                        <?= strtoupper(substr($r['firstName'],0,1) . substr($r['surname'],0,1)) ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.85rem;font-weight:500;color:var(--text-primary)"><?= htmlspecialchars($r['firstName'] . ' ' . $r['surname']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);font-family:var(--font-mono)"><?= htmlspecialchars($r['matricNumber']) ?> · <?= htmlspecialchars($r['courseCode']) ?></div>
                    </div>
                    <div style="text-align:right">
                        <span class="badge badge--success">Verified</span>
                        <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px"><?= date('H:i', strtotime($r['signInTime'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Today's course activity -->
    <div>
        <div class="card mb-24" style="margin-bottom:20px">
            <div class="card-header">
                <span class="card-title">Today's Course Activity</span>
            </div>
            <div class="card-body--flush">
                <?php if (empty($todayCourses)): ?>
                    <div class="empty-state" style="padding:40px 20px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        <div class="empty-state-title">No exams today</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($todayCourses as $tc):
                        $pct = $tc['expected'] > 0 ? round(($tc['verified']/$tc['expected'])*100) : 0;
                    ?>
                    <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                            <div>
                                <span class="td-mono"><?= htmlspecialchars($tc['courseCode']) ?></span>
                                <span style="font-size:.8rem;color:var(--text-secondary);margin-left:8px"><?= htmlspecialchars($tc['courseTitle']) ?></span>
                            </div>
                            <span style="font-size:.78rem;font-family:var(--font-mono);color:var(--signal)"><?= $tc['verified'] ?>/<?= $tc['expected'] ?: '?' ?></span>
                        </div>
                        <div class="gauge-bar-wrap" style="height:4px">
                            <div class="gauge-bar" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header"><span class="card-title">Quick Actions</span></div>
            <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:10px">
                    <a href="<?= APP_URL ?>/modules/students/enroll.php" class="btn btn--ghost" style="justify-content:flex-start">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        Enroll New Student
                    </a>
                    <a href="<?= APP_URL ?>/modules/courses/register.php" class="btn btn--ghost" style="justify-content:flex-start">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                        Register Student for Course
                    </a>
                    <a href="<?= APP_URL ?>/modules/attendance/verify.php" class="btn btn--primary" style="justify-content:flex-start">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20z"/><path d="M9 12l2 2 4-4"/></svg>
                        Launch Exam Verification
                    </a>
                    <a href="<?= APP_URL ?>/modules/reports/index.php" class="btn btn--ghost" style="justify-content:flex-start">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Generate Attendance Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
