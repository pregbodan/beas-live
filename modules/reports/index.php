<?php
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Date range
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$courseId = (int)($_GET['courseId'] ?? 0);

// Overall stats for range
$stats = $db->prepare("
    SELECT
        COUNT(DISTINCT a.studentId) as unique_students,
        COUNT(*) as total_verifications,
        SUM(a.signInStatus='present') as present_count,
        SUM(a.signInStatus='rejected') as rejected_count,
        AVG(CASE WHEN a.signInStatus='present' THEN 1 ELSE 0 END)*100 as success_rate
    FROM attendance a
    WHERE a.attendanceDate BETWEEN ? AND ?
    " . ($courseId ? "AND a.courseId=?" : "") . "
");
$params = [$from, $to];
if ($courseId) $params[] = $courseId;
$stats->execute($params);
$stats = $stats->fetch();

// Daily trend
$trend = $db->prepare("
    SELECT attendanceDate,
           COUNT(*) as total,
           SUM(signInStatus='present') as present,
           SUM(signInStatus='rejected') as rejected
    FROM attendance
    WHERE attendanceDate BETWEEN ? AND ?
    " . ($courseId ? "AND courseId=?" : "") . "
    GROUP BY attendanceDate ORDER BY attendanceDate ASC
");
$trend->execute($params);
$trend = $trend->fetchAll();

// Per-course breakdown
$byCourseSql = "
    SELECT c.courseCode, c.courseTitle, c.level,
           COUNT(a.id) as attempts,
           SUM(a.signInStatus='present') as present,
           SUM(a.signInStatus='rejected') as rejected
    FROM courses c
    LEFT JOIN attendance a ON a.courseId=c.id AND a.attendanceDate BETWEEN ? AND ?
    WHERE c.isActive=1
    GROUP BY c.id ORDER BY attempts DESC
";
$byCourse = $db->prepare($byCourseSql);
$byCourse->execute([$from, $to]);
$byCourse = $byCourse->fetchAll();

// Top students (most verified)
$topStudents = $db->prepare("
    SELECT s.surname, s.firstName, s.matricNumber, s.level,
           COUNT(a.id) as exams_sat
    FROM students s
    JOIN attendance a ON a.studentId=s.id AND a.signInStatus='present'
                     AND a.attendanceDate BETWEEN ? AND ?
    GROUP BY s.id ORDER BY exams_sat DESC LIMIT 10
");
$topStudents->execute([$from, $to]);
$topStudents = $topStudents->fetchAll();

// Verification method breakdown
$methods = $db->prepare("
    SELECT verificationMethod, COUNT(*) as cnt
    FROM attendance WHERE attendanceDate BETWEEN ? AND ?
    GROUP BY verificationMethod
");
$methods->execute([$from, $to]);
$methods = $methods->fetchAll(PDO::FETCH_KEY_PAIR);

$courses = $db->query("SELECT id, courseCode FROM courses WHERE isActive=1 ORDER BY courseCode")->fetchAll();

$trendLabels = array_column($trend, 'attendanceDate');
$trendPresent = array_column($trend, 'present');
$trendRejected = array_column($trend, 'rejected');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reports & Analytics</h1>
        <p class="page-subtitle">Examination attendance and biometric verification metrics</p>
    </div>
    <a href="<?= APP_URL ?>/api/export.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&courseId=<?= $courseId ?>" class="btn btn--primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
    </a>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= $from ?>">
            </div>
            <div class="form-group">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= $to ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Course</label>
                <select name="courseId" class="form-control" style="width:160px">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id']==$courseId?'selected':'' ?>><?= htmlspecialchars($c['courseCode']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn--primary">Apply</button>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card stat-card--electric">
        <div class="stat-icon stat-icon--electric">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-value"><?= number_format($stats['unique_students'] ?? 0) ?></div>
        <div class="stat-label">Unique Students</div>
    </div>
    <div class="stat-card stat-card--signal">
        <div class="stat-icon stat-icon--signal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-value"><?= number_format($stats['present_count'] ?? 0) ?></div>
        <div class="stat-label">Verified Present</div>
    </div>
    <div class="stat-card stat-card--amber">
        <div class="stat-icon stat-icon--amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-value"><?= round($stats['success_rate'] ?? 0, 1) ?>%</div>
        <div class="stat-label">Success Rate</div>
    </div>
    <div class="stat-card stat-card--danger">
        <div class="stat-icon stat-icon--danger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-value"><?= number_format($stats['rejected_count'] ?? 0) ?></div>
        <div class="stat-label">Rejected</div>
    </div>
</div>

<div class="grid-2" style="gap:20px;margin-bottom:20px">
    <!-- Trend Chart -->
    <div class="card">
        <div class="card-header"><span class="card-title">Daily Verification Trend</span></div>
        <div class="card-body">
            <canvas id="trendChart" height="200"></canvas>
        </div>
    </div>

    <!-- Method Pie -->
    <div class="card">
        <div class="card-header"><span class="card-title">Verification Method</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
            <?php
            $total = array_sum($methods) ?: 1;
            $methodLabels = ['fingerprint'=>'Fingerprint','face'=>'Face Recognition','both'=>'Multimodal'];
            $methodColors = ['fingerprint'=>'var(--electric)','face'=>'var(--signal)','both'=>'var(--amber)'];
            foreach ($methodLabels as $key=>$label):
                $cnt = $methods[$key] ?? 0;
                $pct = round(($cnt/$total)*100);
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
                    <span style="color:var(--text-secondary)"><?= $label ?></span>
                    <span style="font-family:var(--font-mono);color:<?= $methodColors[$key] ?>"><?= $cnt ?> (<?= $pct ?>%)</span>
                </div>
                <div class="gauge-bar-wrap">
                    <div class="gauge-bar" style="width:<?= $pct ?>%;background:<?= $methodColors[$key] ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="divider"></div>
            <div style="font-size:.78rem;color:var(--text-muted)">
                Multimodal (fingerprint + face) provides strongest anti-spoofing protection per BEAS security model.
            </div>
        </div>
    </div>
</div>

<!-- Per-course breakdown -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">Course Breakdown</span></div>
    <div class="table-wrap card-body--flush">
        <table>
            <thead>
                <tr>
                    <th>Course</th><th>Level</th>
                    <th>Attempts</th><th>Present</th>
                    <th>Rejected</th><th>Rate</th><th>Bar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($byCourse as $bc):
                    $rate = $bc['attempts'] > 0 ? round(($bc['present']/$bc['attempts'])*100) : 0;
                ?>
                <tr>
                    <td>
                        <span class="td-mono"><?= htmlspecialchars($bc['courseCode']) ?></span>
                        <span style="font-size:.78rem;color:var(--text-muted);margin-left:8px"><?= htmlspecialchars($bc['courseTitle']) ?></span>
                    </td>
                    <td><span class="badge badge--blue"><?= $bc['level'] ?></span></td>
                    <td><?= $bc['attempts'] ?></td>
                    <td class="text-signal"><?= $bc['present'] ?></td>
                    <td class="text-danger"><?= $bc['rejected'] ?></td>
                    <td class="text-mono"><?= $rate ?>%</td>
                    <td style="min-width:100px">
                        <div class="gauge-bar-wrap" style="height:4px">
                            <div class="gauge-bar" style="width:<?= $rate ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top Students -->
<div class="card">
    <div class="card-header"><span class="card-title">Top Verified Students</span></div>
    <div class="table-wrap card-body--flush">
        <table>
            <thead>
                <tr><th>Student</th><th>Matric No.</th><th>Level</th><th>Exams Sat</th></tr>
            </thead>
            <tbody>
                <?php if (empty($topStudents)): ?>
                <tr><td colspan="4"><div class="empty-state" style="padding:30px"><div class="empty-state-title">No data for range</div></div></td></tr>
                <?php else: ?>
                <?php foreach ($topStudents as $i=>$ts): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <span style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted);min-width:20px"><?= $i+1 ?>.</span>
                            <?= htmlspecialchars($ts['surname'].', '.$ts['firstName']) ?>
                        </div>
                    </td>
                    <td class="td-mono"><?= htmlspecialchars($ts['matricNumber']) ?></td>
                    <td><span class="badge badge--blue"><?= $ts['level'] ?></span></td>
                    <td><span style="font-family:var(--font-mono);color:var(--signal);font-weight:700"><?= $ts['exams_sat'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$trendLabelsJson   = json_encode($trendLabels);
$trendPresentJson  = json_encode($trendPresent);
$trendRejectedJson = json_encode($trendRejected);

$inlineScript = <<<JS
// Trend Chart using canvas
(function() {
    const canvas  = document.getElementById('trendChart');
    if (!canvas) return;
    const ctx     = canvas.getContext('2d');
    const labels  = $trendLabelsJson;
    const present = $trendPresentJson;
    const rejected= $trendRejectedJson;

    if (!labels.length) {
        ctx.fillStyle = '#555A72';
        ctx.font = '14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('No data for selected range', canvas.width/2, canvas.height/2);
        return;
    }

    const W = canvas.width  = canvas.offsetWidth;
    const H = canvas.height = 200;
    const pad = {top:20, right:20, bottom:40, left:40};
    const gw  = W - pad.left - pad.right;
    const gh  = H - pad.top  - pad.bottom;
    const maxVal = Math.max(...present, ...rejected, 1);
    const bw  = Math.max(6, gw/labels.length - 6);

    ctx.clearRect(0,0,W,H);

    // Grid lines
    for (let i=0;i<=4;i++) {
        const y = pad.top + (gh/4)*i;
        ctx.strokeStyle = '#252A42';
        ctx.lineWidth   = 1;
        ctx.beginPath(); ctx.moveTo(pad.left,y); ctx.lineTo(W-pad.right,y); ctx.stroke();
        const val = Math.round(maxVal*(1-i/4));
        ctx.fillStyle = '#555A72'; ctx.font = '10px DM Mono, monospace';
        ctx.textAlign = 'right';
        ctx.fillText(val, pad.left-6, y+4);
    }

    labels.forEach((label, i) => {
        const x = pad.left + (gw/labels.length)*i + gw/(labels.length*2);

        // Present bar
        const ph = (present[i]/maxVal)*gh;
        ctx.fillStyle = '#00C896';
        ctx.fillRect(x - bw/2, pad.top + gh - ph, bw*0.5, ph);

        // Rejected bar
        const rh = (rejected[i]/maxVal)*gh;
        ctx.fillStyle = '#FF4B55';
        ctx.fillRect(x, pad.top + gh - rh, bw*0.5, rh);

        // X labels
        ctx.fillStyle = '#555A72'; ctx.font = '9px Inter, sans-serif';
        ctx.textAlign = 'center';
        const d = new Date(label);
        ctx.fillText((d.getMonth()+1)+'/'+(d.getDate()), x, H-pad.bottom+14);
    });

    // Legend
    ctx.fillStyle = '#00C896'; ctx.fillRect(pad.left, H-12, 12, 8);
    ctx.fillStyle = '#8B90A8'; ctx.font='10px Inter,sans-serif'; ctx.textAlign='left';
    ctx.fillText('Present', pad.left+16, H-5);
    ctx.fillStyle = '#FF4B55'; ctx.fillRect(pad.left+80, H-12, 12, 8);
    ctx.fillStyle = '#8B90A8';
    ctx.fillText('Rejected', pad.left+96, H-5);
})();
JS;

require_once __DIR__ . '/../../includes/footer.php'; ?>
