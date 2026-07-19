<?php
$pageTitle = 'Course Registration';
require_once __DIR__ . '/../../includes/auth.php';

$db = getDB();
$studentId = (int)($_GET['studentId'] ?? 0);
$student   = null;

if ($studentId) {
    $s = $db->prepare("SELECT * FROM students WHERE id=?");
    $s->execute([$studentId]);
    $student = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sId     = (int)$_POST['studentId'];
    $cIds    = $_POST['courseIds'] ?? [];
    $semester = sanitize($_POST['semester']);

    // Fetch student for matric
    $st = $db->prepare("SELECT * FROM students WHERE id=?");
    $st->execute([$sId]);
    $st = $st->fetch();

    $added = 0;
    foreach ($cIds as $cId) {
        $course = $db->prepare("SELECT * FROM courses WHERE id=?");
        $course->execute([(int)$cId]);
        $course = $course->fetch();
        if (!$course) continue;

        // Upsert
        $check = $db->prepare("SELECT id FROM course_registrations WHERE matricNumber=? AND courseCode=? AND semester=?");
        $check->execute([$st['matricNumber'], $course['courseCode'], $semester]);
        if (!$check->fetch()) {
            $ins = $db->prepare("INSERT INTO course_registrations (matricNumber,courseCode,courseTitle,courseUnit,level,semester,studentId,courseId,approvedAt,approvedBy,isActive) VALUES (?,?,?,?,?,?,?,?,NOW(),?,1)");
            $ins->execute([$st['matricNumber'],$course['courseCode'],$course['courseTitle'],$course['courseUnit'],$course['level'],$semester,$sId,(int)$cId,currentUser()['id']]);
            $added++;
        }
    }
    redirect(APP_URL.'/modules/students/view.php?id='.$sId, "$added course(s) registered successfully");
}

require_once __DIR__ . '/../../includes/header.php';

// Available courses
$courses = $db->query("SELECT * FROM courses WHERE isActive=1 ORDER BY level, semester, courseCode")->fetchAll();

// Student list for dropdown
$students = $db->query("SELECT id, surname, firstName, matricNumber, level FROM students WHERE isActive=1 ORDER BY surname, firstName")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Course Registration</h1>
        <p class="page-subtitle">Link students to their exam-eligible courses</p>
    </div>
    <a href="<?= APP_URL ?>/modules/courses/index.php" class="btn btn--ghost">← Courses</a>
</div>

<div class="card" style="max-width:700px">
    <div class="card-header"><span class="card-title">Register Student</span></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label">Student <span>*</span></label>
                <select name="studentId" class="form-control" required id="studentSelect">
                    <option value="">Select student…</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id']===$studentId?'selected':'' ?>>
                        <?= htmlspecialchars($s['surname'].', '.$s['firstName'].' — '.$s['matricNumber']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:20px">
                <label class="form-label">Semester <span>*</span></label>
                <select name="semester" class="form-control" required>
                    <option value="first">First Semester</option>
                    <option value="second">Second Semester</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:24px">
                <label class="form-label">Courses to Register <span>*</span></label>
                <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;max-height:340px;overflow-y:auto" id="courseList">
                    <?php $lastLevel = null; ?>
                    <?php foreach ($courses as $c): ?>
                        <?php if ($c['level'] !== $lastLevel): $lastLevel = $c['level']; ?>
                        <div style="padding:8px 14px;background:var(--bg-elevated);font-size:.7rem;font-weight:600;color:var(--text-muted);letter-spacing:.08em;text-transform:uppercase;border-bottom:1px solid var(--border)">
                            <?= $c['level'] ?> Level — <?= ucfirst($c['semester']) ?> Semester
                        </div>
                        <?php endif; ?>
                        <label style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s" onmouseover="this.style.background='var(--bg-elevated)'" onmouseout="this.style.background=''">
                            <input type="checkbox" name="courseIds[]" value="<?= $c['id'] ?>" style="accent-color:var(--electric);width:16px;height:16px;cursor:pointer">
                            <span class="td-mono" style="min-width:90px"><?= htmlspecialchars($c['courseCode']) ?></span>
                            <span style="color:var(--text-secondary);font-size:.84rem"><?= htmlspecialchars($c['courseTitle']) ?></span>
                            <span style="margin-left:auto;color:var(--text-muted);font-size:.75rem"><?= $c['courseUnit'] ?> units</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= APP_URL ?>/modules/students/index.php" class="btn btn--ghost">Cancel</a>
                <button type="submit" class="btn btn--primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Register Selected Courses
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
