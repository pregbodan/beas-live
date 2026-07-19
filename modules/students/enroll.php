<?php
$pageTitle = 'Enroll Student';
require_once __DIR__ . '/../../includes/auth.php';

$db     = getDB();
$editId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$student = null;
$error   = '';

if ($editId) {
    $s = $db->prepare("SELECT * FROM students WHERE id=?");
    $s->execute([$editId]);
    $student = $s->fetch();
    if (!$student) redirect(APP_URL.'/modules/students/index.php','Student not found','error');
    $pageTitle = 'Edit Student';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'surname'     => sanitize($_POST['surname']     ?? ''),
        'firstName'   => sanitize($_POST['firstName']   ?? ''),
        'middleName'  => sanitize($_POST['middleName']  ?? ''),
        'age'         => (int)($_POST['age']            ?? 0),
        'phoneNumber' => sanitize($_POST['phoneNumber'] ?? ''),
        'email'       => sanitize($_POST['email']       ?? ''),
        'department'  => sanitize($_POST['department']  ?? ''),
        'course'      => sanitize($_POST['course']      ?? ''),
        'level'       => sanitize($_POST['level']       ?? ''),
        'matricNumber'=> strtoupper(sanitize($_POST['matricNumber'] ?? '')),
    ];

    $profileUrl = $student['profilePictureUrl'] ?? '';
    if (!empty($_FILES['photo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $error = 'Only JPG/PNG/WEBP files allowed for photos.';
        } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Photo upload failed. Please try again.';
        } else {
            $safeMatric = preg_replace('/[^A-Za-z0-9_-]/', '_', $data['matricNumber']);
            $fname = 'STU_' . ($safeMatric ?: time()) . '_' . time() . '.' . $ext;
            if (!is_dir(PROFILE_PATH) && !mkdir(PROFILE_PATH, 0755, true) && !is_dir(PROFILE_PATH)) {
                $error = 'Failed to create upload directory for photos.';
            } elseif (!move_uploaded_file($_FILES['photo']['tmp_name'], PROFILE_PATH . $fname)) {
                $error = 'Unable to save uploaded photo. Check directory permissions and try again.';
            } else {
                $profileUrl = 'uploads/profiles/' . $fname;
            }
        }
    }

    $fpCaptured = !empty($_POST['fp_captured']) ? 1 : 0;
    $thumbTpl   = $_POST['thumb_template']  ?? ($student['thumbTemplate']  ?? '');
    $indexTpl   = $_POST['index_template']  ?? ($student['indexTemplate']  ?? '');
    $middleTpl  = '';
    $ringTpl    = '';
    $pinkyTpl   = '';
    $faceDesc   = $_POST['face_descriptor'] ?? ($student['faceDescriptor'] ?? '');

    if (!$error) {
        // Validate duplicate matric number and email before insert/update.
        $duplicateSql = 'SELECT id FROM students WHERE (matricNumber = ? OR email = ?)';
        $duplicateParams = [$data['matricNumber'], $data['email']];
        if ($editId) {
            $duplicateSql .= ' AND id <> ?';
            $duplicateParams[] = $editId;
        }
        $check = $db->prepare($duplicateSql);
        $check->execute($duplicateParams);
        if ($existing = $check->fetch()) {
            // Determine which unique field conflicts by checking existing record.
            $conflict = $db->prepare('SELECT matricNumber, email FROM students WHERE id = ?');
            $conflict->execute([$existing['id']]);
            $conflictRow = $conflict->fetch();
            if ($conflictRow) {
                if ($conflictRow['matricNumber'] === $data['matricNumber']) {
                    $error = 'A student with that matric number already exists.';
                } elseif ($conflictRow['email'] === $data['email']) {
                    $error = 'A student with that email address already exists.';
                } else {
                    $error = 'A student record conflict was detected. Please verify matric number and email.';
                }
            } else {
                $error = 'A student record conflict was detected. Please verify matric number and email.';
            }
        }
    }

    if (!$error) {
        if ($editId) {
            $stmt = $db->prepare("UPDATE students SET surname=?,firstName=?,middleName=?,age=?,phoneNumber=?,email=?,
                department=?,course=?,level=?,matricNumber=?,profilePictureUrl=?,fingerprintsCaptured=?,
                thumbTemplate=?,indexTemplate=?,middleTemplate=?,ringTemplate=?,pinkyTemplate=?,
                faceDescriptor=?,updated_at=NOW() WHERE id=?");
            $stmt->execute([
                $data['surname'],$data['firstName'],$data['middleName'],$data['age'],
                $data['phoneNumber'],$data['email'],$data['department'],$data['course'],
                $data['level'],$data['matricNumber'],$profileUrl,$fpCaptured,
                $thumbTpl,$indexTpl,$middleTpl,$ringTpl,$pinkyTpl,$faceDesc,$editId
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO students
                (surname,firstName,middleName,age,phoneNumber,email,department,course,level,
                 matricNumber,profilePictureUrl,fingerprintsCaptured,thumbTemplate,indexTemplate,
                 middleTemplate,ringTemplate,pinkyTemplate,faceDescriptor)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $data['surname'],$data['firstName'],$data['middleName'],$data['age'],
                $data['phoneNumber'],$data['email'],$data['department'],$data['course'],
                $data['level'],$data['matricNumber'],$profileUrl,$fpCaptured,
                $thumbTpl,$indexTpl,$middleTpl,$ringTpl,$pinkyTpl,$faceDesc
            ]);
            $editId = (int)$db->lastInsertId();
        }
        redirect(APP_URL.'/modules/students/view.php?id='.$editId,
            'Student '.($editId ? 'updated' : 'enrolled').' successfully');
    }
}

// â”€â”€ Pre-compute all PHP values needed in JS (avoids heredoc PHP tags) â”€â”€â”€â”€â”€â”€
$jsInitCount    = $student
    ? ((int)!empty($student['thumbTemplate']) + (int)!empty($student['indexTemplate']))
    : 0;
$jsIsEdit       = $editId ? 'true' : 'false';
$jsExistingData = [];
foreach (['thumb','index'] as $f) {
    $jsExistingData[$f] = ($student && !empty($student[$f.'Template'])) ? 'true' : 'false';
}
$jsExistingJson = json_encode($jsExistingData);
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><?= $student ? 'Edit Student' : 'Enroll New Student' ?></h1>
        <p class="page-subtitle">Register student biometrics and academic profile</p>
    </div>
    <a href="<?= APP_URL ?>/modules/students/index.php" class="btn btn--ghost">â† Back</a>
</div>

<?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- U.are.U 4500 Status Bar -->
<div id="readerStatusBar" style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;margin-bottom:20px;font-size:.82rem">
    <div id="readerIndicator" style="width:10px;height:10px;border-radius:50%;background:var(--text-muted);flex-shrink:0"></div>
    <span id="readerStatusText" style="color:var(--text-muted)">Initialising U.are.U 4500 readerâ€¦</span>
    <span style="margin-left:auto;font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted)">HID Authentication Service</span>
</div>

<form method="POST" enctype="multipart/form-data" id="enrollForm">
<?php if ($editId): ?>
    <input type="hidden" name="id" value="<?= $editId ?>">
<?php endif; ?>
<div class="grid-2" style="gap:20px;align-items:start">

    <!-- â”€â”€ Left: Bio Data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div>
        <div class="card" style="margin-bottom:20px">
            <div class="card-header"><span class="card-title">Personal Information</span></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Surname <span>*</span></label>
                        <input type="text" name="surname" class="form-control" required value="<?= htmlspecialchars($student['surname'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">First Name <span>*</span></label>
                        <input type="text" name="firstName" class="form-control" required value="<?= htmlspecialchars($student['firstName'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middleName" class="form-control" value="<?= htmlspecialchars($student['middleName'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" class="form-control" min="16" max="60" value="<?= htmlspecialchars($student['age'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phoneNumber" class="form-control" value="<?= htmlspecialchars($student['phoneNumber'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px">
            <div class="card-header"><span class="card-title">Academic Details</span></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label class="form-label">Matric Number <span>*</span></label>
                        <input type="text" name="matricNumber" class="form-control text-mono" required
                               placeholder="e.g. FUO/19/ENG/CPE/001"
                               value="<?= htmlspecialchars($student['matricNumber'] ?? '') ?>"
                               <?= $student ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department <span>*</span></label>
                        <select name="department" class="form-control" required>
                            <option value="">Selectâ€¦</option>
                            <?php foreach (['Computer Engineering','Electrical Engineering','Mechanical Engineering','Civil Engineering'] as $dept): ?>
                            <option value="<?= $dept ?>" <?= ($student['department']??'')===$dept?'selected':'' ?>><?= $dept ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Programme</label>
                        <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($student['course'] ?? 'B.Eng') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Level <span>*</span></label>
                        <select name="level" class="form-control" required>
                            <option value="">Selectâ€¦</option>
                            <?php foreach ([100,200,300,400,500] as $l): ?>
                            <option value="<?= $l ?>" <?= ($student['level']??'')==$l?'selected':'' ?>><?= $l ?> Level</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">Student Photo</label>
                        <?php if (!empty($student['profilePictureUrl'])): ?>
                        <div style="margin-bottom:8px">
                            <img src="<?= APP_URL.'/'.$student['profilePictureUrl'] ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="photo" class="form-control" accept="image/*" onchange="previewPhoto(this)">
                        <div id="photoPreview" style="margin-top:10px"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Face Enrollment -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Face Enrollment</span>
                <span id="faceStatus" class="badge badge--muted">Not Captured</span>
            </div>
            <div class="card-body">
                <div id="insightfaceHealth" style="margin-bottom:12px;padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:.78rem;color:var(--text-muted);background:var(--bg-elevated)">
                    Checking Railway health...
                </div>
                <?php if ($student && !empty($student['faceDescriptor'])): ?>
                <div class="alert alert--success" style="margin-bottom:12px">Face descriptor previously stored.</div>
                <?php endif; ?>
                <div style="position:relative;border-radius:8px;overflow:hidden;background:#000;aspect-ratio:4/3;margin-bottom:12px">
                    <video id="enrollVideo" autoplay playsinline style="width:100%;height:100%;object-fit:cover;display:block"></video>
                    <canvas id="faceOverlayCanvas" style="position:absolute;top:0;left:0;width:100%;height:100%"></canvas>
                    <div id="faceGuide" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:160px;height:200px;border:2px dashed rgba(30,111,255,.5);border-radius:50%;pointer-events:none"></div>
                    <canvas id="enrollCanvas" style="display:none"></canvas>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn--ghost" onclick="startCamera()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="4"/><path d="M20.94 11A8.994 8.994 0 1 1 11 3.06"/></svg>
                        Start Camera
                    </button>
                            <button type="button" id="flipPreviewBtn" class="btn btn--ghost" onclick="toggleMirror()">Flip Preview</button>
                    <button type="button" class="btn btn--primary" onclick="captureEnrollFace()" id="captureFaceBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72"/></svg>
                        Capture Face
                    </button>
                </div>
                <input type="hidden" name="face_descriptor" id="face_descriptor" value="<?= htmlspecialchars($student['faceDescriptor'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- â”€â”€ Right: Fingerprint â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div>
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <span class="card-title">Fingerprint Capture â€” U.are.U 4500</span>
                <span id="fpStatus" class="badge badge--muted">Not Captured</span>
            </div>
            <div class="card-body">

                <!-- Finger selector -->
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:20px" id="fingerGrid">
                    <?php
                    $fingers = ['thumb'=>'Thumb','index'=>'Index'];
                    foreach ($fingers as $key => $label):
                        $isCaptured = $student && !empty($student[$key.'Template']);
                    ?>
                    <button type="button"
                            class="finger-btn <?= $isCaptured ? 'captured' : '' ?>"
                            data-finger="<?= $key ?>"
                            onclick="selectFinger('<?= $key ?>', this)"
                            id="fbtn-<?= $key ?>"
                            title="<?= $label ?> finger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28">
                            <path d="M12 2C8.68 2 6 4.68 6 8v8c0 3.32 2.68 6 6 6s6-2.68 6-6V8c0-3.32-2.68-6-6-6z"/>
                            <path d="M9 12c0-1.66 1.34-3 3-3s3 1.34 3 3v4"/>
                        </svg>
                        <span><?= $label ?></span>
                        <div class="finger-indicator" id="indicator-<?= $key ?>"><?= $isCaptured ? 'âœ“' : 'Â·' ?></div>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Selected finger label -->
                <div style="text-align:center;margin-bottom:14px;font-size:.8rem;color:var(--text-muted)">
                    Selected: <span id="selectedFingerLabel" style="color:var(--electric);font-weight:600">Thumb</span>
                </div>

                <!-- Fingerprint scan canvas + ridge visualiser -->
                <div style="position:relative;background:#0A0C12;border:2px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:16px" id="scanArea">
                    <!-- Ridge pattern canvas -->
                    <canvas id="ridgeCanvas" width="340" height="340" style="display:block;width:100%;height:auto"></canvas>
                    <!-- Overlay messages -->
                    <div id="scanOverlay" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;background:rgba(10,12,18,.8)">
                        <svg id="fpIcon" viewBox="0 0 64 64" fill="none" stroke="#555A72" stroke-width="1.4" width="72" height="72">
                            <path d="M32 8C19 8 8 18.5 8 32c0 8.4 4 15.8 10.2 20.4"/>
                            <path d="M32 8c13 0 24 10.5 24 24 0 8.4-4 15.8-10.2 20.4"/>
                            <path d="M32 16c8.8 0 16 7.2 16 16 0 5.4-2.7 10.2-6.7 13.1"/>
                            <path d="M32 16c-8.8 0-16 7.2-16 16 0 5.4 2.7 5.1 3.3 6.6"/>
                            <path d="M32 24c4.4 0 8 3.6 8 8 0 2.7-1.3 5.1-3.3 6.6"/>
                            <path d="M32 24c-4.4 0-8 3.6-8 8 0 2.7 1.3 5.1 3.3 6.6"/>
                            <circle cx="32" cy="32" r="2" fill="#555A72"/>
                        </svg>
                        <div id="scanMsg" style="font-size:.85rem;color:var(--text-secondary);text-align:center;padding:0 20px">Place finger on U.are.U 4500 reader,<br>then click Scan</div>
                        <div id="scanSubMsg" style="font-size:.72rem;color:var(--text-muted);font-family:var(--font-mono)">MINDTCT minutiae extraction ready</div>
                        <!-- Quality meter -->
                        <div id="qualityMeter" style="display:none;width:200px">
                            <div style="font-size:.7rem;color:var(--text-muted);margin-bottom:4px;text-align:center">Image Quality</div>
                            <div style="height:5px;background:var(--bg-hover);border-radius:3px;overflow:hidden">
                                <div id="qualityBar" style="height:100%;width:0%;background:var(--signal);border-radius:3px;transition:width .6s ease"></div>
                            </div>
                            <div id="qualityLabel" style="font-size:.7rem;color:var(--signal);text-align:center;margin-top:3px;font-family:var(--font-mono)">â€”</div>
                        </div>
                        <!-- Minutiae count -->
                        <div id="minutiaeCount" style="display:none;font-size:.72rem;font-family:var(--font-mono);color:var(--electric)"></div>
                    </div>
                </div>

                <!-- Scan button -->
                <div style="display:flex;gap:10px;margin-bottom:16px">
                    <button type="button" class="btn btn--primary" id="scanBtn" onclick="startFingerprintLiveCapture()" style="flex:1;justify-content:center" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M12 2C8.68 2 6 4.68 6 8v8c0 3.32 2.68 6 6 6s6-2.68 6-6V8c0-3.32-2.68-6-6-6z"/>
                        </svg>
                        <span id="scanBtnLabel">Live Capture</span>
                    </button>
                    <button type="button" class="btn btn--ghost" onclick="clearCurrentFinger()" title="Re-scan this finger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.17"/></svg>
                    </button>
                </div>

                <div id="scanProgress" style="display:none" class="alert alert--warning">
                    <div class="spinner" style="width:15px;height:15px;margin-right:8px;flex-shrink:0"></div>
                    <span id="scanProgressMsg">Waiting for finger placementâ€¦</span>
                </div>
                <div id="scanResult" style="display:none"></div>

                <div style="margin-top:14px;font-size:.8rem;color:var(--text-muted);text-align:center">
                    <span id="capturedCount"><?= $jsInitCount ?></span>/2 fingers captured
                </div>

                <!-- Hidden template inputs -->
                <input type="hidden" name="fp_captured" id="fp_captured" value="<?= $jsInitCount >= 2 ? '1' : '0' ?>">
                <?php foreach (['thumb','index'] as $f): ?>
                <input type="hidden" name="<?= $f ?>_template" id="tpl_<?= $f ?>" value="<?= htmlspecialchars($student[$f.'Template'] ?? '') ?>">
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions" style="border:none;padding:0">
            <a href="<?= APP_URL ?>/modules/students/index.php" class="btn btn--ghost">Cancel</a>
            <button type="submit" class="btn btn--primary btn--lg">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <?= $student ? 'Update Student' : 'Save & Enroll' ?>
            </button>
        </div>
    </div>
</div>
</form>

<!-- faceapi.js for real face descriptor extraction -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script src="<?= APP_URL ?>/scripts/websdk.client.ui.js"></script>
<script src="<?= APP_URL ?>/scripts/fingerprint.sdk.min.js"></script>
<script src="<?= APP_URL ?>/scripts/beas-fingerprint.js"></script>

<script>
// ============================================================
// PHP values injected safely as JS variables (no heredoc PHP tags)
// ============================================================
const INIT_CAPTURE_COUNT  = <?= $jsInitCount ?>;
const IS_EDIT_MODE        = <?= $jsIsEdit ?>;
const EXISTING_FINGERS    = <?= $jsExistingJson ?>;
const APP_URL             = '<?= APP_URL ?>';
const READER_HOST         = <?= json_encode(FINGERPRINT_READER_HOST) ?>;
const READER_PORT         = <?= json_encode(FINGERPRINT_READER_PORT) ?>;
const READER_PROTOCOL     = <?= json_encode(FINGERPRINT_READER_PROTOCOL) ?>;
const READER_CLIENT_PATH  = <?= json_encode(FINGERPRINT_READER_CLIENT_PATH) ?>;

// ============================================================
// READER STATUS
// ============================================================
function setReaderStatus(state, msg) {
    const dot  = document.getElementById('readerIndicator');
    const text = document.getElementById('readerStatusText');
    const colors = { ready:'#00C896', scanning:'#F5A623', error:'#FF4B55', idle:'#555A72' };
    dot.style.background  = colors[state] || colors.idle;
    text.style.color      = state === 'error' ? '#FF4B55' : state === 'ready' ? '#00C896' : '#8B90A8';
    text.textContent      = msg;
}

// ============================================================
// U.are.U 4500  â€”  DigitalPersona Web SDK / HID Auth Bridge
//
// HID Authentication Service exposes a local WebSocket on
// https://127.0.0.1:52181/get_connection  (DigitalPersona Web SDK handshake)
//
// The Web SDK handles the secure local connection and the page-level
// JSON protocol below stays the same once the channel is open.
// ============================================================
const INSIGHTFACE_REQUEST_TIMEOUT_MS = <?= json_encode((int) INSIGHTFACE_CLIENT_TIMEOUT_MS) ?>;
const INSIGHTFACE_HEALTH_URL = '<?= APP_URL ?>/api/face_health.php';
const INSIGHTFACE_HEALTH_TIMEOUT_MS = <?= json_encode(((int) (defined('INSIGHTFACE_HEALTH_TIMEOUT') ? INSIGHTFACE_HEALTH_TIMEOUT : 5)) * 1000) ?>;
let fingerprintReader = null;
let readerReady       = false;
let readerLastMessage = '';
let insightFaceHealth = { state: 'unknown', detail: 'Checking Railway health...' };
let insightFaceHealthTimer = null;

function getReaderUrl() {
    return 'https://' + READER_HOST + ':' + READER_PORT;
}

function getFingerprintReader() {
    if (fingerprintReader) return fingerprintReader;

    fingerprintReader = new BeasFingerprint.ReaderController({
        port: READER_PORT,
        debug: false,
    });

    fingerprintReader.setStatusHandler((state, msg) => {
        readerReady = state === 'ready' || state === 'scanning';
        if (state === 'idle' || state === 'error') {
            liveCaptureStarted = false;
        }
        readerLastMessage = msg || '';
        setReaderStatus(state, msg);
    });

    fingerprintReader.setErrorHandler((error) => {
        readerReady = false;
        liveCaptureStarted = false;
        readerLastMessage = error?.message || String(error || 'Fingerprint communication failed.');
    });

    return fingerprintReader;
}

function setInsightFaceHealthUi(state, detail) {
    const el = document.getElementById('insightfaceHealth');
    if (!el) return;

    const palette = {
        checking: { color: '#8B90A8', bg: 'var(--bg-elevated)', border: 'var(--border)' },
        healthy: { color: '#00C896', bg: 'rgba(0,200,150,.08)', border: 'rgba(0,200,150,.25)' },
        unhealthy: { color: '#FF4B55', bg: 'rgba(255,75,85,.08)', border: 'rgba(255,75,85,.25)' },
        unknown: { color: '#8B90A8', bg: 'var(--bg-elevated)', border: 'var(--border)' },
    };
    const theme = palette[state] || palette.unknown;
    el.textContent = detail || (state === 'healthy' ? 'Railway service is healthy.' : 'Railway service status unknown.');
    el.style.color = theme.color;
    el.style.background = theme.bg;
    el.style.borderColor = theme.border;
}

function fingerprintDriverHelpText(readerUrl) {
    return [
        'No fingerprint reader detected at ' + readerUrl + '.',
        'If the device is not visible, install the required HID/Crossmatch drivers:',
        'https://crossmatch.hid.gl/lite-client/',
        'https://www.hidglobal.com/drivers/49061',
        'Then reconnect the reader and try again.',
    ].join(' ');
}

async function probeInsightFaceHealth() {
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timeoutId = controller ? setTimeout(() => controller.abort(), INSIGHTFACE_HEALTH_TIMEOUT_MS) : null;
    try {
        setInsightFaceHealthUi('checking', 'Checking Railway health...');
        const resp = await fetch(INSIGHTFACE_HEALTH_URL, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller ? controller.signal : undefined,
        });
        const data = await resp.json().catch(() => ({}));
        const healthy = resp.ok && data.healthy === true;
        insightFaceHealth = {
            state: healthy ? 'healthy' : 'unhealthy',
            detail: data.detail || data.error || (healthy ? 'Railway service is healthy.' : 'Railway service is unreachable or cold.'),
            checkedAt: Date.now(),
        };
        setInsightFaceHealthUi(insightFaceHealth.state, insightFaceHealth.detail);
        return healthy;
    } catch (error) {
        const detail = error?.name === 'AbortError'
            ? 'Railway health check timed out after ' + Math.round(INSIGHTFACE_HEALTH_TIMEOUT_MS / 1000) + ' seconds.'
            : (error?.message || 'Railway service is unreachable.');
        insightFaceHealth = { state: 'unhealthy', detail, checkedAt: Date.now() };
        setInsightFaceHealthUi('unhealthy', detail);
        return false;
    } finally {
        if (timeoutId) clearTimeout(timeoutId);
    }
}

async function ensureInsightFaceHealthy() {
    const fresh = insightFaceHealth.checkedAt && (Date.now() - insightFaceHealth.checkedAt) < 30000;
    if (fresh && insightFaceHealth.state === 'healthy') {
        return true;
    }
    return probeInsightFaceHealth();
}

async function initReader() {
    const readerUrl = getReaderUrl();
    setReaderStatus('idle', 'Connecting to fingerprint reader via DigitalPersona bridgeâ€¦');
    try {
        const readers = await getFingerprintReader().refreshReaders();
        readerReady = Array.isArray(readers) && readers.length > 0;
        if (!readerReady) {
            showScanResult('error', fingerprintDriverHelpText(readerUrl) + ' ' + (readerLastMessage || 'Connect the reader and try again.'));
            return false;
        }
        document.getElementById('scanBtn').disabled = false;
        document.getElementById('scanBtnLabel').textContent = 'Live Capture';
        document.getElementById('scanBtn').title = 'Ensure the reader is listening automatically';
        return true;
    } catch (e) {
        readerReady = false;
        setReaderStatus('error', 'Reader unavailable: ' + e.message);
        document.getElementById('scanBtn').disabled = false;
        document.getElementById('scanBtnLabel').textContent = 'Retry Connection';
        document.getElementById('scanBtn').title = 'Try connecting to the fingerprint reader again';
        showScanResult(
            'error',
            'Cannot reach DigitalPersona bridge at ' + readerUrl + '. ' +
            'If the reader is not seen by Windows, install the required drivers: ' +
            'https://crossmatch.hid.gl/lite-client/ and https://www.hidglobal.com/drivers/49061. ' +
            (e?.message || 'Communication failed.')
        );
        return false;
    }
}

// ============================================================
// FINGERPRINT CAPTURE FLOW
// ============================================================
let selectedFinger  = 'thumb';
let captureCount    = INIT_CAPTURE_COUNT;
let capturedFingers = {};
let capturedTemplateHashes = {};
let liveCaptureStarted = false;
let liveCaptureBusy = false;

// Restore previously captured fingers from edit mode
Object.entries(EXISTING_FINGERS).forEach(([f, exists]) => {
    if (exists === 'true') {
        capturedFingers[f] = true;
        const ind = document.getElementById('indicator-' + f);
        if (ind) ind.textContent = 'âœ“';
    }
});

function selectFinger(finger, btn) {
    selectedFinger = finger;
    document.querySelectorAll('.finger-btn').forEach(b => b.style.borderColor = '');
    btn.style.borderColor = 'var(--electric)';
    document.getElementById('selectedFingerLabel').textContent =
        finger.charAt(0).toUpperCase() + finger.slice(1);
    resetScanArea();
}

function setDisplay(id, value) {
    const el = document.getElementById(id);
    if (el && el.style) el.style.display = value;
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function fingerprintTemplateHash(template) {
    if (!template) return '';
    return String(template);
}

async function startFingerprintLiveCapture() {
    if (!readerReady) {
        const connected = await initReader();
        if (!connected || !readerReady) {
            return false;
        }
    }

    if (liveCaptureStarted) {
        return true;
    }

    const reader = getFingerprintReader();
    reader.setSampleHandler(handleFingerprintSample);

    try {
        await reader.start(Fingerprint.SampleFormat.PngImage);
        liveCaptureStarted = true;
        setDisplay('scanProgress', 'flex');
        setText('scanProgressMsg', 'Waiting for ' + selectedFinger + ' finger...');
        setDisplay('scanOverlay', 'flex');
        setText('scanMsg', 'Place ' + selectedFinger + ' finger on the sensor');
        setText('scanBtnLabel', 'Live Capture Active');
        setReaderStatus('scanning', 'Live fingerprint capture active. Present a finger on the scanner.');
        showScanResult('info', 'Live fingerprint capture started. Present a finger on the scanner.');
        return true;
    } catch (error) {
        liveCaptureStarted = false;
        setReaderStatus('error', 'Reader unavailable: ' + error.message);
        showScanResult('error', 'Cannot start live fingerprint capture: ' + error.message);
        return false;
    }
}

async function handleFingerprintSample(result) {
    if (liveCaptureBusy) {
        return;
    }

    liveCaptureBusy = true;
    try {
        setDisplay('scanProgress', 'none');
        setDisplay('scanOverlay', 'flex');
        setReaderStatus('scanning', 'Fingerprint detected. Capturing live snapshot...');

        if (result.sample) {
            await drawFingerprintRidges(result.sample, result.quality);
        } else {
            drawMinutiaePattern(result.minutiaeCount || 35);
        }

        const q = result.quality || 0;
        document.getElementById('qualityMeter')?.style && (document.getElementById('qualityMeter').style.display = 'block');
        const qualityBar = document.getElementById('qualityBar');
        if (qualityBar && qualityBar.style) {
            qualityBar.style.width = q + '%';
            qualityBar.style.background = q >= 60 ? 'var(--signal)' : q >= 40 ? 'var(--amber)' : 'var(--danger)';
        }
        document.getElementById('qualityLabel').textContent = 'Quality: ' + q + '% â€” ' + (q >= 60 ? 'Good' : q >= 40 ? 'Acceptable' : 'Poor â€” re-scan');
        document.getElementById('minutiaeCount')?.style && (document.getElementById('minutiaeCount').style.display = 'block');
        const minutiaeCountEl = document.getElementById('minutiaeCount');
        if (minutiaeCountEl) {
            minutiaeCountEl.textContent = (result.minutiaeCount || '?') + ' minutiae detected';
        }

        if (q < 40) {
            showScanResult('warning', 'Image quality too low (' + q + '%). Clean the sensor and try again.');
            setReaderStatus('scanning', 'Waiting for a clearer fingerprint...');
            return;
        }

        const template = result.sample || result.sampleData || '';
        const templateHash = fingerprintTemplateHash(template);
        const existingFinger = capturedTemplateHashes[templateHash];
        if (existingFinger && existingFinger !== selectedFinger) {
            const templateField = document.getElementById('tpl_' + selectedFinger);
            if (templateField) templateField.value = '';
            showScanResult('warning', 'This fingerprint image already exists on ' + existingFinger + '. Please place a different finger.');
            setReaderStatus('scanning', 'Waiting for a different finger...');
            return;
        }

        const templateField = document.getElementById('tpl_' + selectedFinger);
        if (templateField) templateField.value = template;

        if (!capturedFingers[selectedFinger]) {
            capturedFingers[selectedFinger] = true;
            captureCount++;
        }
        if (templateHash) {
            capturedTemplateHashes[templateHash] = selectedFinger;
        }

        const indicator = document.getElementById('indicator-' + selectedFinger);
        if (indicator) indicator.textContent = 'âœ“';
        const fingerButton = document.getElementById('fbtn-' + selectedFinger);
        if (fingerButton) fingerButton.classList.add('captured');

        updateCaptureStatus();
        showScanResult('success', selectedFinger.charAt(0).toUpperCase() + selectedFinger.slice(1)
            + ' fingerprint captured. Quality: ' + q + '% | Minutiae: ' + (result.minutiaeCount || '?'));

        try {
            await getFingerprintReader().stop();
        } catch (_) {
            // The reader may already be idle; ignore stop errors here.
        }
        liveCaptureStarted = false;

        const order = ['thumb','index'];
        const nextFinger = order.find(f => !capturedFingers[f]);
        if (nextFinger) {
            setTimeout(() => {
                selectFinger(nextFinger, document.getElementById('fbtn-' + nextFinger));
                startFingerprintLiveCapture().catch(() => {});
            }, 1200);
        }

        setReaderStatus('scanning', 'Live fingerprint capture active. Present another finger to continue.');
    } catch (error) {
        setDisplay('scanProgress', 'none');
        setReaderStatus('scanning', 'Live capture active - waiting for the next finger');
        showScanResult('error', 'Capture failed: ' + error.message);
        drawErrorPattern();
    } finally {
        liveCaptureBusy = false;
    }
}

async function captureFingerprint() {
    if (!readerReady) {
        const connected = await initReader();
        if (!connected || !readerReady) {
            return;
        }
    }

    const scanBtn = document.getElementById('scanBtn');
    scanBtn.disabled = true;
    document.getElementById('scanBtnLabel').textContent = 'Scanningâ€¦';
    document.getElementById('scanProgress').style.display = 'flex';
    document.getElementById('scanProgressMsg').textContent = 'Place ' + selectedFinger + ' finger on sensorâ€¦';
    document.getElementById('scanResult').style.display = 'none';
    document.getElementById('scanOverlay').style.display = 'flex';
    document.getElementById('scanMsg').textContent = 'Place ' + selectedFinger + ' finger on the sensor';
    document.getElementById('qualityMeter').style.display = 'none';
    document.getElementById('minutiaeCount').style.display = 'none';

    setReaderStatus('scanning', 'Scanning ' + selectedFinger + ' fingerâ€¦');

    try {
        const result = await getFingerprintReader().acquire(Fingerprint.SampleFormat.PngImage, 15000);

        // Success â€” draw ridge pattern from WSQ data
        document.getElementById('scanProgress').style.display = 'none';
        setReaderStatus('ready', 'Capture successful â€” U.are.U 4500 ready');

        if (result.sample) {
            await drawFingerprintRidges(result.sample, result.quality);
        } else {
            drawMinutiaePattern(result.minutiaeCount || 35);
        }

        // Show quality
        const q = result.quality || 0;
        document.getElementById('qualityMeter').style.display = 'block';
        document.getElementById('qualityBar').style.width = q + '%';
        document.getElementById('qualityBar').style.background = q >= 60 ? 'var(--signal)' : q >= 40 ? 'var(--amber)' : 'var(--danger)';
        document.getElementById('qualityLabel').textContent = 'Quality: ' + q + '% â€” ' + (q >= 60 ? 'Good' : q >= 40 ? 'Acceptable' : 'Poor â€” re-scan');
        document.getElementById('minutiaeCount').style.display = 'block';
        document.getElementById('minutiaeCount').textContent = (result.minutiaeCount || '?') + ' minutiae detected';

        if (q < 40) {
            showScanResult('warning', 'Image quality too low (' + q + '%). Clean the sensor and re-scan.');
            scanBtn.disabled = false;
            document.getElementById('scanBtnLabel').textContent = 'Live Capture';
            return;
        }

        // Store template
        const template = result.sample || result.sampleData || '';
        document.getElementById('tpl_' + selectedFinger).value = template;

        if (!capturedFingers[selectedFinger]) {
            capturedFingers[selectedFinger] = true;
            captureCount++;
        }

        const indicator = document.getElementById('indicator-' + selectedFinger);
        if (indicator) indicator.textContent = 'âœ“';
        document.getElementById('fbtn-' + selectedFinger).classList.add('captured');

        updateCaptureStatus();
        showScanResult('success', selectedFinger.charAt(0).toUpperCase() + selectedFinger.slice(1)
            + ' fingerprint captured. Quality: ' + q + '% | Minutiae: ' + (result.minutiaeCount || '?'));

        // Auto-advance to next uncaptured finger
        const order = ['thumb','index'];
        const nextFinger = order.find(f => !capturedFingers[f]);
        if (nextFinger) {
            setTimeout(() => {
                selectFinger(nextFinger, document.getElementById('fbtn-' + nextFinger));
            }, 1200);
        }

    } catch(e) {
        document.getElementById('scanProgress').style.display = 'none';
        setReaderStatus('ready', 'Ready â€” last capture failed');
        showScanResult('error', 'Capture failed: ' + e.message);
        drawErrorPattern();
    }

    scanBtn.disabled = false;
    document.getElementById('scanBtnLabel').textContent = 'Live Capture';
}

function updateCaptureStatus() {
    document.getElementById('capturedCount').textContent = captureCount;
    const badge = document.getElementById('fpStatus');
    document.getElementById('fp_captured').value = captureCount >= 2 ? '1' : '0';

    if (captureCount >= 2) {
        document.getElementById('fp_captured').value = '1';
        badge.className   = 'badge badge--success';
        badge.textContent = 'Thumb + Index Captured âœ“';
    } else if (captureCount > 0) {
        badge.className   = 'badge badge--amber';
        badge.textContent = captureCount + '/2 Captured';
    }
}

function clearCurrentFinger() {
    const templateField = document.getElementById('tpl_' + selectedFinger);
    if (templateField) templateField.value = '';

    for (const [hash, finger] of Object.entries(capturedTemplateHashes)) {
        if (finger === selectedFinger) {
            delete capturedTemplateHashes[hash];
        }
    }

    if (capturedFingers[selectedFinger]) {
        delete capturedFingers[selectedFinger];
        captureCount = Math.max(0, captureCount - 1);
    }
    const indicator = document.getElementById('indicator-' + selectedFinger);
    if (indicator) indicator.textContent = 'Â·';
    const fingerButton = document.getElementById('fbtn-' + selectedFinger);
    if (fingerButton) fingerButton.classList.remove('captured');
    updateCaptureStatus();
    resetScanArea();
    showScanResult('info', selectedFinger + ' template cleared. Re-scan to replace.');
}

function showScanResult(type, msg) {
    const el  = document.getElementById('scanResult');
    const map = { success:'alert--success', error:'alert--error', warning:'alert--warning', info:'alert--info' };
    el.style.display = 'block';
    el.innerHTML = '<div class="alert ' + (map[type]||'alert--info') + '">' + msg + '</div>';
}

function resetScanArea() {
    const canvas = document.getElementById('ridgeCanvas');
    const ctx    = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('scanOverlay').style.display = 'flex';
    document.getElementById('scanMsg').textContent = 'Place ' + selectedFinger + ' finger on U.are.U 4500 for live capture';
    document.getElementById('qualityMeter').style.display   = 'none';
    document.getElementById('minutiaeCount').style.display  = 'none';
    document.getElementById('scanResult').style.display     = 'none';
}

// ============================================================
// RIDGE PATTERN VISUALISER
// Decodes WSQ base64 image and draws it on the canvas,
// then overlays detected minutiae points.
// ============================================================
async function drawFingerprintRidges(wsqBase64, quality) {
    const canvas  = document.getElementById('ridgeCanvas');
    const ctx     = canvas.getContext('2d');
    const overlay = document.getElementById('scanOverlay');

    // Try rendering as image (browser won't decode WSQ natively,
    // but DigitalPersona SDK can also return PNG/BMP alongside WSQ)
    // The HID service often returns a PNG preview in msg.preview
    // Fall back to procedural ridge art if no renderable image
    try {
        await new Promise((resolve, reject) => {
            const img = new Image();
            // Try treating base64 as PNG first (SDK may embed it)
            img.onload = () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                // Tint green for quality
                ctx.globalCompositeOperation = 'multiply';
                ctx.fillStyle = quality >= 60 ? 'rgba(0,220,160,.15)' : 'rgba(245,166,35,.15)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.globalCompositeOperation = 'source-over';
                overlay.style.display = 'none';
                resolve();
            };
            img.onerror = () => reject();
            img.src = 'data:image/png;base64,' + wsqBase64;
        });
    } catch {
        // WSQ not renderable as PNG â€” draw procedural ridge pattern
        drawMinutiaePattern(Math.floor(30 + quality * 0.4));
        overlay.style.display = 'none';
    }
}

function drawMinutiaePattern(minutiaeCount) {
    const canvas = document.getElementById('ridgeCanvas');
    const ctx    = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;

    ctx.clearRect(0, 0, W, H);

    // Background
    ctx.fillStyle = '#0A0C12';
    ctx.fillRect(0, 0, W, H);

    // Draw concentric ridge arcs (fingerprint whorl/loop pattern)
    const cx = W / 2, cy = H * 0.48;
    const lineColor = 'rgba(30,111,255,0.55)';

    ctx.strokeStyle = lineColor;
    ctx.lineWidth   = 1.4;

    const ridgeCount = 22;
    for (let i = 1; i <= ridgeCount; i++) {
        const rx = (i / ridgeCount) * (W * 0.46);
        const ry = (i / ridgeCount) * (H * 0.42);
        ctx.beginPath();
        ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
        ctx.stroke();
    }

    // Add some arch ridges at bottom
    for (let i = 0; i < 8; i++) {
        const y = cy + H * 0.15 + i * 12;
        ctx.beginPath();
        ctx.moveTo(cx - W * 0.45, y);
        ctx.quadraticCurveTo(cx, y - 30 + i * 5, cx + W * 0.45, y);
        ctx.stroke();
    }

    // Minutiae points (ridge endings = circles, bifurcations = triangles)
    const seed = minutiaeCount * 7919;
    function prng(n) { return ((Math.sin(n + seed) * 43758.5453) % 1 + 1) % 1; }

    const margin = 40;
    for (let i = 0; i < minutiaeCount; i++) {
        const angle = prng(i * 3)     * Math.PI * 2;
        const dist  = prng(i * 3 + 1) * Math.min(W, H) * 0.38;
        const mx    = cx + Math.cos(angle) * dist;
        const my    = cy + Math.sin(angle) * dist * 0.85;

        if (mx < margin || mx > W - margin || my < margin || my > H - margin) continue;

        const isBifurcation = i % 3 === 0;

        if (isBifurcation) {
            // Triangle (bifurcation)
            const sz = 5;
            ctx.beginPath();
            ctx.moveTo(mx, my - sz);
            ctx.lineTo(mx + sz, my + sz);
            ctx.lineTo(mx - sz, my + sz);
            ctx.closePath();
            ctx.strokeStyle = '#00C896';
            ctx.lineWidth   = 1.5;
            ctx.stroke();
        } else {
            // Circle (ridge ending)
            ctx.beginPath();
            ctx.arc(mx, my, 4, 0, Math.PI * 2);
            ctx.strokeStyle = '#F5A623';
            ctx.lineWidth   = 1.5;
            ctx.stroke();
            ctx.fillStyle   = 'rgba(245,166,35,.25)';
            ctx.fill();
        }
    }

    // Legend
    ctx.font      = '10px DM Mono, monospace';
    ctx.fillStyle = '#555A72';
    ctx.fillText('â— Ridge ending', 12, H - 28);
    ctx.fillText('â–² Bifurcation', 12, H - 14);
    ctx.fillText(minutiaeCount + ' minutiae', W - 90, H - 14);

    document.getElementById('scanOverlay').style.display = 'none';
}

function drawErrorPattern() {
    const canvas = document.getElementById('ridgeCanvas');
    const ctx    = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = 'rgba(255,75,85,.05)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    document.getElementById('scanOverlay').style.display = 'flex';
    document.getElementById('scanMsg').textContent = 'Scan failed â€” try again';
}

let scanLineY = 0, scanLineAnim = null;
function animateScanLine() {
    const canvas = document.getElementById('ridgeCanvas');
    const ctx    = canvas.getContext('2d');
    if (scanLineAnim) cancelAnimationFrame(scanLineAnim);
    scanLineY = 0;
    function frame() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#0A0C12';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        // Scan line
        const grad = ctx.createLinearGradient(0, scanLineY - 20, 0, scanLineY + 20);
        grad.addColorStop(0,   'transparent');
        grad.addColorStop(0.5, 'rgba(30,111,255,.7)');
        grad.addColorStop(1,   'transparent');
        ctx.fillStyle = grad;
        ctx.fillRect(0, scanLineY - 20, canvas.width, 40);
        scanLineY += 4;
        if (scanLineY < canvas.height + 20) {
            scanLineAnim = requestAnimationFrame(frame);
        }
    }
    frame();
}

// ============================================================
// FACE ENROLLMENT â€” face-api.js FaceNet 128-dim descriptor extraction
// ============================================================
let faceStream    = null;
let faceApiLoaded = false;
let faceDetectInterval = null;
let previewMirrored = false;

async function loadFaceApi() {
    if (faceApiLoaded) return;
    try {
        const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
        await Promise.all([
            faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        faceApiLoaded = true;
    } catch(e) {
        console.error('face-api.js model load failed:', e);
        document.getElementById('faceStatus').className = 'badge badge--danger';
        document.getElementById('faceStatus').textContent = 'Face model load failed';
        throw e;
    }
}

async function startCamera() {
    document.getElementById('faceStatus').textContent = 'Loading modelsâ€¦';
    document.getElementById('faceStatus').className   = 'badge badge--amber';
    try {
        await loadFaceApi();
    } catch (e) {
        alert('Face model load failed. Check your internet connection and retry.');
        return;
    }
    try {
        faceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width:640, height:480 } });
        const video = document.getElementById('enrollVideo');
        video.srcObject = faceStream;
        video.onloadedmetadata = () => {
            document.getElementById('captureFaceBtn').disabled = false;
            document.getElementById('faceStatus').className   = 'badge badge--amber';
            document.getElementById('faceStatus').textContent = 'Camera Active';
            startFaceDetectionLoop();
            applyMirrorToPreview();
        };
    } catch(e) {
        alert('Camera error: ' + e.message);
    }
}

function applyMirrorToPreview() {
    const v = document.getElementById('enrollVideo');
    const overlay = document.getElementById('faceOverlayCanvas');
    const guide = document.getElementById('faceGuide');
    if (!v) return;
    if (previewMirrored) {
        v.style.transform = 'scaleX(-1)';
        if (overlay) overlay.style.transform = 'scaleX(-1)';
        if (guide) guide.style.transform = 'translate(-50%,-50%) scaleX(-1)';
        if (document.getElementById('flipPreviewBtn')) document.getElementById('flipPreviewBtn').textContent = 'Unflip Preview';
    } else {
        v.style.transform = '';
        if (overlay) overlay.style.transform = '';
        if (guide) guide.style.transform = 'translate(-50%,-50%)';
        if (document.getElementById('flipPreviewBtn')) document.getElementById('flipPreviewBtn').textContent = 'Flip Preview';
    }
}

function toggleMirror() {
    previewMirrored = !previewMirrored;
    applyMirrorToPreview();
}

function startFaceDetectionLoop() {
    const video  = document.getElementById('enrollVideo');
    const canvas = document.getElementById('faceOverlayCanvas');
    faceDetectInterval = setInterval(async () => {
        if (!faceApiLoaded || video.readyState < 2) return;
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        const detections = await faceapi
            .detectAllFaces(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
            .withFaceLandmarks();
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        faceapi.draw.drawDetections(canvas, detections);
        faceapi.draw.drawFaceLandmarks(canvas, detections);
        if (detections.length === 1) {
            document.getElementById('faceStatus').className   = 'badge badge--signal';
            document.getElementById('faceStatus').textContent = 'Face Detected âœ“';
        } else if (detections.length === 0) {
            document.getElementById('faceStatus').className   = 'badge badge--amber';
            document.getElementById('faceStatus').textContent = 'No Face Detected';
        }
    }, 400);
}

async function captureEnrollFace() {
    const video  = document.getElementById('enrollVideo');
    const canvas = document.getElementById('enrollCanvas');
    const captureBtn = document.getElementById('captureFaceBtn');
    captureBtn.disabled = true;
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    if (previewMirrored) {
        ctx.save();
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        ctx.restore();
    } else {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    }

    const snapshot = canvas.toDataURL('image/jpeg', 0.9);
    let previewImg = video.parentNode.querySelector('[data-face-snapshot="enroll"]');
    if (!previewImg) {
        previewImg = document.createElement('img');
        previewImg.dataset.faceSnapshot = 'enroll';
        previewImg.style.cssText = 'width:100%;border-radius:8px;display:block;margin-bottom:12px';
        video.parentNode.insertBefore(previewImg, video);
    }
    previewImg.src = snapshot;

    document.getElementById('faceStatus').className   = 'badge badge--amber';
    document.getElementById('faceStatus').textContent = 'Detecting faceâ€¦';

    try {
        if (!faceApiLoaded) throw new Error('Models not loaded');
        const detection = await faceapi
            .detectSingleFace(canvas, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
            .withFaceLandmarks();

        if (!detection) throw new Error('No face detected in captured frame. Reposition and try again.');

        if (!(await ensureInsightFaceHealthy())) {
            throw new Error(insightFaceHealth.detail || 'Railway service is unreachable or cold.');
        }

        // Send captured image to server for InsightFace embedding (512-dim)
        const imgB64 = snapshot.split(',')[1];
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), INSIGHTFACE_REQUEST_TIMEOUT_MS);
        const resp = await fetch('../../api/face_embed.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ probeImage: imgB64 }),
            signal: controller.signal
        });
        clearTimeout(timeoutId);
        const j = await resp.json().catch(() => ({}));
        if (!resp.ok) {
            throw new Error(j.detail || j.error || ('Server returned status ' + resp.status));
        }
        if (j.embedding && Array.isArray(j.embedding)) {
            // store InsightFace 512-dim embedding
            document.getElementById('face_descriptor').value = j.embedding.map(v => Number(v).toFixed(6)).join(',');
        } else {
            throw new Error(j.detail || 'InsightFace embedding unavailable');
        }

        document.getElementById('faceStatus').className   = 'badge badge--success';
        document.getElementById('faceStatus').textContent = 'Face Enrolled âœ“ (512-dim)';

        if (faceDetectInterval) clearInterval(faceDetectInterval);
        if (faceStream) faceStream.getTracks().forEach(t => t.stop());
        video.style.display = 'none';
        document.getElementById('faceOverlayCanvas').style.display = 'none';

        const img = document.createElement('img');
        img.src   = canvas.toDataURL('image/jpeg', 0.9);
        img.style.cssText = 'width:100%;border-radius:8px;display:block';
        video.parentNode.insertBefore(img, video);

    } catch(e) {
        if (e.name === 'AbortError') {
            e = new Error('InsightFace request timed out after ' + Math.round(INSIGHTFACE_REQUEST_TIMEOUT_MS / 1000) + ' seconds');
        }
        document.getElementById('faceStatus').className   = 'badge badge--danger';
        document.getElementById('faceStatus').textContent = 'Capture failed';
        alert('Face capture error: ' + e.message);
    } finally {
        captureBtn.disabled = false;
    }
}

// ============================================================
// PHOTO PREVIEW
// ============================================================
function previewPhoto(input) {
    const preview = document.getElementById('photoPreview');
    if (!input.files || !input.files[0]) { preview.innerHTML = ''; return; }
    const reader = new FileReader();
    reader.onload = e => {
        preview.innerHTML = '<img src="' + e.target.result + '" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--electric)">';
    };
    reader.readAsDataURL(input.files[0]);
}

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    startFingerprintLiveCapture();
    updateCaptureStatus();
    probeInsightFaceHealth().catch(() => {});
    if (insightFaceHealthTimer) clearInterval(insightFaceHealthTimer);
    insightFaceHealthTimer = setInterval(() => {
        if (insightFaceHealth.state !== 'healthy') {
            probeInsightFaceHealth().catch(() => {});
        }
    }, 30000);
    // Style finger-select default (thumb active)
    const firstBtn = document.getElementById('fbtn-thumb');
    if (firstBtn) firstBtn.style.borderColor = 'var(--electric)';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

