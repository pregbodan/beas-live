<?php
$pageTitle = 'Student Profile';
require_once __DIR__ . '/../../includes/header.php';

$db  = getDB();
$usr = currentUser();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL.'/modules/students/index.php','Invalid student','error');

$student = $db->prepare("SELECT * FROM students WHERE id=?");
$student->execute([$id]);
$student = $student->fetch();
if (!$student) redirect(APP_URL.'/modules/students/index.php','Not found','error');

// Role-based visibility flags
// invigilator → no raw biometric templates, no face descriptor, no edit button
// admin        → can see photo, biometric status, edit
// superadmin   → full access including raw template lengths
$canEdit         = in_array($usr['role'], ['admin','superadmin']);
$canSeeBiometric = in_array($usr['role'], ['admin','superadmin']);
$canSeePhoto     = true; // all roles can see photo — useful for identity checks
$canSeeTemplates = $usr['role'] === 'superadmin';

$courses = $db->prepare("SELECT cr.*, c.courseTitle, c.courseUnit, c.level, c.semester, c.courseCode
    FROM course_registrations cr JOIN courses c ON cr.courseId=c.id
    WHERE cr.studentId=? AND cr.isActive=1 ORDER BY c.level, c.semester");
$courses->execute([$id]);
$courses = $courses->fetchAll();

$attendance = $db->prepare("SELECT a.*, c.courseCode, c.courseTitle
    FROM attendance a JOIN courses c ON a.courseId=c.id
    WHERE a.studentId=? ORDER BY a.signInTime DESC LIMIT 15");
$attendance->execute([$id]);
$attendance = $attendance->fetchAll();

// Stats
$totalPresent  = $db->prepare("SELECT COUNT(*) FROM attendance WHERE studentId=? AND signInStatus='present'");
$totalPresent->execute([$id]);
$totalPresent = (int)$totalPresent->fetchColumn();

$totalCourses = count($courses);
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><?= htmlspecialchars($student['firstName'].' '.$student['surname']) ?></h1>
        <p class="page-subtitle text-mono"><?= htmlspecialchars($student['matricNumber']) ?></p>
    </div>
    <div class="btn-group">
        <?php if ($canEdit): ?>
        <a href="<?= APP_URL ?>/modules/students/enroll.php?id=<?= $id ?>" class="btn btn--ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Profile
        </a>
        <a href="<?= APP_URL ?>/modules/courses/register.php?studentId=<?= $id ?>" class="btn btn--primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            Register Course
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/modules/attendance/verify.php" class="btn btn--ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
            Verify
        </a>
    </div>
</div>

<div class="grid-2" style="gap:20px;align-items:start">

    <!-- ── LEFT COLUMN ──────────────────────────────────────── -->
    <div>

        <!-- Identity Card -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header"><span class="card-title">Identity</span></div>
            <div class="card-body">

                <!-- Photo section — visible to all roles -->
                <div style="display:flex;gap:20px;align-items:flex-start;margin-bottom:20px">
                    <div style="flex-shrink:0">
                        <?php if ($canSeePhoto && !empty($student['profilePictureUrl'])): ?>
                            <img src="<?= APP_URL.'/'.$student['profilePictureUrl'] ?>"
                                 alt="<?= htmlspecialchars($student['firstName']) ?>"
                                 style="width:96px;height:96px;border-radius:12px;object-fit:cover;border:2px solid var(--border);display:block"
                                 onerror="this.style.display='none';document.getElementById('avatarFallback').style.display='flex'">
                            <div id="avatarFallback" style="display:none;width:96px;height:96px;border-radius:12px;background:linear-gradient(135deg,#2D3A6E,#1E6FFF);align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff">
                                <?= strtoupper(substr($student['firstName'],0,1).substr($student['surname'],0,1)) ?>
                            </div>
                        <?php else: ?>
                            <div style="width:96px;height:96px;border-radius:12px;background:linear-gradient(135deg,#2D3A6E,#1E6FFF);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff">
                                <?= strtoupper(substr($student['firstName'],0,1).substr($student['surname'],0,1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--text-primary);line-height:1.3">
                            <?= htmlspecialchars($student['surname'].', '.$student['firstName'].(' '.$student['middleName'] ?? '')) ?>
                        </div>
                        <div style="font-family:var(--font-mono);font-size:.82rem;color:var(--electric);margin:5px 0">
                            <?= htmlspecialchars($student['matricNumber']) ?>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
                            <span class="badge badge--blue"><?= htmlspecialchars($student['level']) ?> Level</span>
                            <?php if ($student['fingerprintsCaptured']): ?>
                                <span class="badge badge--success">Biometrics ✓</span>
                            <?php else: ?>
                                <span class="badge badge--danger">No Biometrics</span>
                            <?php endif; ?>
                            <?php if (!empty($student['faceDescriptor'])): ?>
                                <span class="badge badge--signal">Face ✓</span>
                            <?php endif; ?>
                            <span class="badge badge--<?= $student['isActive']?'success':'muted' ?>"><?= $student['isActive']?'Active':'Inactive' ?></span>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Personal details -->
                <?php
                $fields = [
                    'Department' => $student['department'],
                    'Programme'  => $student['course'],
                    'Email'      => $student['email'],
                    'Phone'      => $student['phoneNumber'],
                    'Age'        => $student['age'] ? $student['age'].' years' : null,
                    'Enrolled'   => date('d M Y', strtotime($student['enrolled_at'])),
                ];
                foreach ($fields as $k => $v): if (!$v) continue; ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border);font-size:.84rem">
                    <span style="color:var(--text-muted)"><?= $k ?></span>
                    <span style="color:var(--text-primary);text-align:right;max-width:60%"><?= htmlspecialchars($v) ?></span>
                </div>
                <?php endforeach; ?>

                <!-- Role-restricted: raw face descriptor length -->
                <?php if ($canSeeTemplates && !empty($student['faceDescriptor'])): ?>
                <div style="display:flex;justify-content:space-between;padding:9px 0;font-size:.84rem;border-bottom:1px solid var(--border)">
                    <span style="color:var(--text-muted)">Face Descriptor</span>
                    <span style="font-family:var(--font-mono);font-size:.75rem;color:var(--electric)">
                        512-dim · <?= strlen($student['faceDescriptor']) ?> chars
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Exam Stats -->
        <div class="card">
            <div class="card-header"><span class="card-title">Exam Statistics</span></div>
            <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;text-align:center">
                <div style="padding:16px;background:var(--bg-elevated);border-radius:8px">
                    <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--signal)"><?= $totalPresent ?></div>
                    <div style="font-size:.73rem;color:var(--text-muted);text-transform:uppercase;margin-top:4px">Exams Verified</div>
                </div>
                <div style="padding:16px;background:var(--bg-elevated);border-radius:8px">
                    <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--electric)"><?= $totalCourses ?></div>
                    <div style="font-size:.73rem;color:var(--text-muted);text-transform:uppercase;margin-top:4px">Registered Courses</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── RIGHT COLUMN ─────────────────────────────────────── -->
    <div>

        <!-- Biometric Status — admin/superadmin only -->
        <?php if ($canSeeBiometric): ?>
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <span class="card-title">Biometric Status</span>
                <?php if ($canEdit): ?>
                <a href="<?= APP_URL ?>/modules/students/enroll.php?id=<?= $id ?>" class="btn btn--ghost btn--sm">Re-enroll</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <!-- Fingerprint grid -->
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:16px;text-align:center">
                    <?php foreach (['thumb'=>'Thumb','index'=>'Index','middle'=>'Middle','ring'=>'Ring','pinky'=>'Pinky'] as $key=>$label): ?>
                    <div style="padding:12px 6px;background:var(--bg-elevated);border-radius:8px;border:1px solid <?= !empty($student[$key.'Template']) ? 'rgba(0,200,150,.3)' : 'var(--border)' ?>">
                        <div style="font-size:1.3rem"><?= !empty($student[$key.'Template']) ? '✅' : '❌' ?></div>
                        <div style="font-size:.68rem;color:var(--text-muted);margin-top:4px"><?= $label ?></div>
                        <?php if ($canSeeTemplates && !empty($student[$key.'Template'])): ?>
                        <div style="font-size:.6rem;color:var(--text-muted);font-family:var(--font-mono);margin-top:2px"><?= strlen($student[$key.'Template']) ?>B</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Face descriptor row -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-elevated);border-radius:8px;border:1px solid var(--border)">
                    <div>
<div style="font-size:.78rem;font-weight:500;color:var(--text-secondary)">Face Descriptor (InsightFace)</div>
                         <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px">512-dimensional embedding</div>
                    </div>
                    <span class="badge badge--<?= !empty($student['faceDescriptor']) ? 'success' : 'danger' ?>">
                        <?= !empty($student['faceDescriptor']) ? 'Enrolled ✓' : 'Not enrolled' ?>
                    </span>
                </div>

                <!-- Fingerprint scanner info -->
                <div style="margin-top:12px;font-size:.73rem;color:var(--text-muted);display:flex;gap:6px;align-items:center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Device: U.are.U 4500 · Algorithm: MINDTCT + BOZORTH3 · Face: SSD MobileNet V1
                </div>
            </div>
        </div>
        <?php endif; // canSeeBiometric ?>

        <!-- Registered Courses -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <span class="card-title">Registered Courses</span>
                <span class="badge badge--blue"><?= count($courses) ?></span>
            </div>
            <?php if (empty($courses)): ?>
            <div class="empty-state" style="padding:30px">
                <div class="empty-state-title">No courses registered</div>
                <?php if ($canEdit): ?><a href="<?= APP_URL ?>/modules/courses/register.php?studentId=<?= $id ?>" class="btn btn--ghost btn--sm" style="margin-top:8px">Register Now</a><?php endif; ?>
            </div>
            <?php else: ?>
            <div class="card-body--flush">
                <?php foreach ($courses as $c): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 16px;border-bottom:1px solid var(--border);font-size:.84rem">
                    <div>
                        <span class="td-mono"><?= htmlspecialchars($c['courseCode']) ?></span>
                        <span style="color:var(--text-secondary);margin-left:8px"><?= htmlspecialchars($c['courseTitle']) ?></span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <span style="color:var(--text-muted);font-size:.75rem"><?= $c['courseUnit'] ?> units</span>
                        <span class="badge badge--success">Registered</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Attendance History -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Attendance History</span>
                <a href="<?= APP_URL ?>/modules/attendance/index.php?search=<?= urlencode($student['matricNumber']) ?>" class="btn btn--ghost btn--sm">Full Log</a>
            </div>
            <?php if (empty($attendance)): ?>
            <div class="empty-state" style="padding:30px"><div class="empty-state-title">No attendance records</div></div>
            <?php else: ?>
            <div class="table-wrap card-body--flush">
                <table>
                    <thead>
                        <tr><th>Course</th><th>Date</th><th>In</th><th>Method</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $a): ?>
                        <tr>
                            <td class="td-mono"><?= htmlspecialchars($a['courseCode']) ?></td>
                            <td style="white-space:nowrap"><?= date('d M', strtotime($a['attendanceDate'])) ?></td>
                            <td style="font-family:var(--font-mono);font-size:.78rem"><?= $a['signInTime'] ? date('H:i', strtotime($a['signInTime'])) : '—' ?></td>
                            <td>
                                <span class="badge badge--<?= ($a['verificationMethod']==='fingerprint')?'blue':'amber' ?>" style="font-size:.65rem">
                                    <?= ucfirst($a['verificationMethod'] ?? 'fp') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge--<?= $a['signInStatus']==='present'?'success':'danger' ?>">
                                    <?= ucfirst($a['signInStatus']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
