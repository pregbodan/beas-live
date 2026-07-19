<?php
$pageTitle = 'Exam Verification';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$courses = $db->query("SELECT * FROM courses WHERE isActive=1 ORDER BY level, courseCode")->fetchAll();
$preselect = (int)($_GET['courseId'] ?? 0);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Exam Verification</h1>
        <p class="page-subtitle">Real-time biometric identity check — BOZORTH3 fingerprint matching</p>
    </div>
    <div class="btn-group">
        <span class="system-badge">
            <span class="pulse-dot"></span>
            NBIS Engine Ready
        </span>
    </div>
</div>

<!-- Session Selector -->
<div class="card mb-24" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">1. Select Exam Session</span></div>
    <div class="card-body">
        <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:200px">
                <label class="form-label">Course</label>
                <select class="form-control" id="courseSelect">
                    <option value="">Select course…</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" data-code="<?= htmlspecialchars($c['courseCode']) ?>" data-title="<?= htmlspecialchars($c['courseTitle']) ?>" <?= $c['id']===$preselect?'selected':'' ?>>
                        <?= htmlspecialchars($c['courseCode']) ?> — <?= htmlspecialchars($c['courseTitle']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Exam Date</label>
                <input type="date" class="form-control" id="examDate" value="<?= date('Y-m-d') ?>">
            </div>
            <button type="button" class="btn btn--primary" onclick="initSession()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Start Session
            </button>
        </div>
        <div id="sessionInfo" style="display:none;margin-top:16px" class="alert alert--info"></div>
    </div>
</div>

<!-- Verification Workspace -->
<div id="verifyWorkspace" style="display:none">
    <div class="verify-container">
        <!-- Fingerprint Panel -->
        <div>
            <div class="card" style="margin-bottom:16px">
                <div class="card-header">
                    <span class="card-title">2. Biometric Capture</span>
                    <div style="display:flex;gap:8px">
                        <button class="btn btn--ghost btn--sm" id="modeBtn" onclick="toggleMode()">Switch to Face</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Fingerprint Mode -->
                    <div id="fpMode">
                        <div class="fingerprint-zone" id="fpZone">
                            <div class="fingerprint-icon">
                                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M32 8C19 8 8 18.5 8 32c0 8.4 4 15.8 10.2 20.4"/>
                                    <path d="M32 8c13 0 24 10.5 24 24 0 8.4-4 15.8-10.2 20.4"/>
                                    <path d="M32 16c8.8 0 16 7.2 16 16 0 5.4-2.7 10.2-6.7 13.1"/>
                                    <path d="M32 16c-8.8 0-16 7.2-16 16 0 5.4 2.7 10.2 6.7 13.1"/>
                                    <path d="M32 24c4.4 0 8 3.6 8 8 0 2.7-1.3 5.1-3.3 6.6"/>
                                    <path d="M32 24c-4.4 0-8 3.6-8 8 0 2.7 1.3 5.1 3.3 6.6"/>
                                    <circle cx="32" cy="32" r="2"/>
                                    <path d="M32 34v14"/>
                                </svg>
                            </div>
                            <div id="fpZoneMsg" style="font-size:.85rem;color:var(--text-secondary)">Waiting for fingerprint...</div>
                            <div style="font-size:.73rem;color:var(--text-muted)">DigitalPersona 4500 · MINDTCT extraction</div>
                        </div>

                        <div style="margin-top:16px">
                            <label class="form-label" style="margin-bottom:6px">Which finger?</label>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <?php foreach (['thumb'=>'Thumb','index'=>'Index'] as $k=>$v): ?>
                                <button type="button" class="finger-select-btn <?= $k==='thumb'?'active':'' ?>" data-finger="<?= $k ?>" onclick="selectFinger('<?= $k ?>', this)">
                                    <?= $v ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Face Mode -->
                    <div id="faceMode" style="display:none">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                            <div>
                                <div class="card-title" style="margin:0">Face Verification</div>
                                <div style="font-size:.82rem;color:var(--text-secondary)">Capture and match the enrolled face</div>
                            </div>
                            <span id="faceStatus" class="badge badge--muted">Not Captured</span>
                        </div>
                        <div id="insightfaceHealth" style="margin-bottom:12px;padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:.78rem;color:var(--text-muted);background:var(--bg-elevated)">
                            Checking Railway health...
                        </div>
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
                                Match Face
                            </button>
                        </div>
                    </div>
                </div>
            </div>

<!-- Match Score -->
             <div class="card" id="scoreCard" style="display:none">
                 <div class="card-body">
                     <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:8px" id="scoreLabelText">Match Score</div>
                     <div class="score-gauge">
                         <div class="gauge-bar-wrap">
                             <div class="gauge-bar" id="scoreBar" style="width:0%"></div>
                         </div>
                         <div class="gauge-label" id="scoreValue">—</div>
                     </div>
                     <div style="font-size:.72rem;color:var(--text-muted);margin-top:6px" id="thresholdDisplay">Threshold: —</div>
                 </div>
             </div>
        </div>

        <!-- Result Panel -->
        <div>
            <div class="card" id="resultCard">
                <div class="card-header"><span class="card-title">3. Verification Result</span></div>
                <div class="card-body">
                    <div id="resultWaiting" style="text-align:center;padding:40px 20px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="56" height="56" style="color:var(--text-muted);margin:0 auto 12px;display:block">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <div style="color:var(--text-muted);font-size:.85rem">Awaiting biometric scan…<br>
                        <span style="font-size:.75rem">Start a session and place finger on scanner</span></div>
                    </div>

                    <div id="resultVerified" style="display:none">
                        <div class="student-match-card">
                            <div id="matchAvatar" class="match-avatar" style="display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;color:var(--electric)">?</div>
                            <div>
                                <div class="match-name" id="matchName">—</div>
                                <div class="match-matric" id="matchMatric">—</div>
                                <div class="match-meta" id="matchMeta">—</div>
                            </div>
                        </div>
                        <div style="margin-top:16px;padding:12px;background:rgba(0,200,150,.07);border:1px solid rgba(0,200,150,.2);border-radius:8px">
                            <div style="font-size:.78rem;color:var(--signal);font-weight:600">✓ IDENTITY CONFIRMED</div>
                            <div style="font-size:.75rem;color:var(--text-secondary);margin-top:4px" id="matchEligibility">—</div>
                        </div>
                        <button class="btn btn--success" style="width:100%;justify-content:center;margin-top:14px" onclick="markAttendance(true)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            Mark Present & Allow Entry
                        </button>
                    </div>

                    <div id="resultRejected" style="display:none">
                        <div style="padding:20px;background:rgba(255,75,85,.07);border:1px solid rgba(255,75,85,.2);border-radius:8px;text-align:center">
                            <div style="font-size:1.5rem;margin-bottom:8px">⛔</div>
                            <div style="font-weight:600;color:var(--danger)" id="rejectReason">Match failed</div>
                            <div style="font-size:.8rem;color:var(--text-muted);margin-top:6px" id="rejectDetail">—</div>
                        </div>
                        <button class="btn btn--ghost" style="width:100%;justify-content:center;margin-top:14px" onclick="resetVerify()">
                            Try Again
                        </button>
                    </div>

                    <div id="resultSuccess" style="display:none">
                        <div style="padding:24px;text-align:center">
                            <div style="font-size:3rem;margin-bottom:8px">✅</div>
                            <div style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--signal)">Entry Granted</div>
                            <div style="font-size:.8rem;color:var(--text-secondary);margin-top:6px" id="successDetail">—</div>
                        </div>
                        <button class="btn btn--primary" style="width:100%;justify-content:center" onclick="resetVerify()">
                            Next Student →
                        </button>
                    </div>
                </div>
            </div>

            <!-- Session Counter -->
            <div class="card" style="margin-top:16px">
                <div class="card-body" style="display:flex;justify-content:space-around;text-align:center">
                    <div>
                        <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--signal)" id="cntVerified">0</div>
                        <div style="font-size:.73rem;color:var(--text-muted);text-transform:uppercase">Verified</div>
                    </div>
                    <div style="width:1px;background:var(--border)"></div>
                    <div>
                        <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--danger)" id="cntRejected">0</div>
                        <div style="font-size:.73rem;color:var(--text-muted);text-transform:uppercase">Rejected</div>
                    </div>
                    <div style="width:1px;background:var(--border)"></div>
                    <div>
                        <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--text-secondary)" id="cntTotal">0</div>
                        <div style="font-size:.73rem;color:var(--text-muted);text-transform:uppercase">Total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Log -->
    <div class="card" style="margin-top:20px">
        <div class="card-header">
            <span class="card-title">Session Log</span>
            <button class="btn btn--ghost btn--sm" onclick="document.getElementById('liveLog').innerHTML=''">Clear</button>
        </div>
        <div id="liveLog" style="height:180px;overflow-y:auto;padding:12px;font-family:var(--font-mono);font-size:.75rem;color:var(--text-secondary);background:var(--bg-base)">
            <div style="color:var(--text-muted)">[SYSTEM] BEAS Verification Engine v<?= APP_VERSION ?> initialized</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script src="<?= APP_URL ?>/scripts/websdk.client.ui.js"></script>
<script src="<?= APP_URL ?>/scripts/fingerprint.sdk.min.js"></script>
<script src="<?= APP_URL ?>/scripts/beas-fingerprint.js"></script>

<script>
// =============================================
// BEAS Verification Engine
// =============================================
const INSIGHTFACE_REQUEST_TIMEOUT_MS = <?= json_encode((int) INSIGHTFACE_CLIENT_TIMEOUT_MS) ?>;
const INSIGHTFACE_HEALTH_URL = '<?= APP_URL ?>/api/face_health.php';
const INSIGHTFACE_HEALTH_TIMEOUT_MS = <?= json_encode((int) INSIGHTFACE_HEALTH_TIMEOUT * 1000) ?>;
let sessionData = { courseId: null, courseCode: '', courseTitle: '', examDate: '', sessionId: '', active: false };
let currentMatch = null;
let selectedFinger = 'thumb';
let verifyMode = 'fingerprint';
let stats = { verified: 0, rejected: 0 };
let faceApiLoaded = false;
let faceStream = null;
let faceDetectInterval = null;
let previewMirrored = false;
const READER_PORT = <?= json_encode(FINGERPRINT_READER_PORT) ?>;

let fingerprintReader = null;
let readerReady = false;
let liveCaptureStarted = false;
let liveCaptureBusy = false;
let insightFaceHealth = { state: 'unknown', detail: 'Checking Railway health...' };
let insightFaceHealthTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    initFingerprintReader().catch(() => {});
    probeInsightFaceHealth().catch(() => {});
    if (insightFaceHealthTimer) clearInterval(insightFaceHealthTimer);
    insightFaceHealthTimer = setInterval(() => {
        if (insightFaceHealth.state !== 'healthy') {
            probeInsightFaceHealth().catch(() => {});
        }
    }, 30000);
});

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
        log(msg, state === 'error' ? 'error' : state === 'ready' ? 'success' : 'info');
    });

    fingerprintReader.setErrorHandler((error) => {
        readerReady = false;
        liveCaptureStarted = false;
        log(error?.message || String(error || 'Fingerprint communication failed.'), 'error');
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

async function initFingerprintReader() {
    try {
        const readers = await getFingerprintReader().refreshReaders();
        readerReady = Array.isArray(readers) && readers.length > 0;
        if (!readerReady) {
            log('No fingerprint readers detected. Connect the scanner and try again.', 'warn');
        }
        return readerReady;
    } catch (error) {
        readerReady = false;
        log(error?.message || String(error || 'Fingerprint communication failed.'), 'error');
        return false;
    }
}

async function startFingerprintLiveCapture() {
    if (!sessionData.active || verifyMode === 'face') {
        return false;
    }

    if (!readerReady) {
        const connected = await initFingerprintReader();
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
        document.getElementById('fpZoneMsg').textContent = 'Waiting for fingerprint...';
        log('Live fingerprint capture started. Present a finger on the scanner.', 'success');
        return true;
    } catch (error) {
        liveCaptureStarted = false;
        log(error?.message || String(error || 'Fingerprint communication failed.'), 'error');
        return false;
    }
}

async function handleFingerprintSample(result) {
    if (liveCaptureBusy || !sessionData.active || verifyMode === 'face') {
        return;
    }

    liveCaptureBusy = true;
    const zone = document.getElementById('fpZone');
    zone.className = 'fingerprint-zone scanning';
    document.getElementById('fpZoneMsg').textContent = 'Fingerprint detected. Matching...';
    log('Fingerprint sample acquired', 'success');

    try {
        const resp = await fetch('../../api/verify.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                courseId: sessionData.courseId,
                finger: selectedFinger,
                sessionId: sessionData.sessionId,
                examDate: sessionData.examDate,
                mode: 'fingerprint',
                liveTemplate: result.sample
            })
        });

        const text = await resp.text();
        if (!resp.ok) throw new Error('Server returned status ' + resp.status);
        if (!text.trim().startsWith('{') && !text.trim().startsWith('[')) {
            throw new Error('Invalid JSON response: ' + text.slice(0, 120));
        }

        zone.className = 'fingerprint-zone ready';
        document.getElementById('fpZoneMsg').textContent = 'Waiting for next fingerprint...';
        handleResult(JSON.parse(text));
    } catch (error) {
        zone.className = 'fingerprint-zone fail';
        document.getElementById('fpZoneMsg').textContent = 'Scan failed. Please try again.';
        log('Fingerprint scan failed: ' + error.message, 'error');
    } finally {
        liveCaptureBusy = false;
    }
}

function log(msg, type = 'info') {
    const el = document.getElementById('liveLog');
    const colors = {info:'var(--text-secondary)', success:'var(--signal)', warn:'var(--amber)', error:'var(--danger)'};
    const time = new Date().toLocaleTimeString('en-GB');
    el.innerHTML += `<div style="color:${colors[type]}"><span style="color:var(--text-muted)">[${time}]</span> ${msg}</div>`;
    el.scrollTop = el.scrollHeight;
}

async function initSession() {
    const sel = document.getElementById('courseSelect');
    if (!sel.value) { alert('Please select a course'); return; }
    const opt = sel.options[sel.selectedIndex];

    sessionData = {
        courseId: parseInt(sel.value),
        courseCode: opt.dataset.code,
        courseTitle: opt.dataset.title,
        examDate: document.getElementById('examDate').value,
        sessionId: 'SES-' + Math.random().toString(36).substr(2,9).toUpperCase(),
        active: true
    };

    document.getElementById('sessionInfo').style.display = 'block';
    document.getElementById('sessionInfo').innerHTML = 
        `<strong>Session Active:</strong> ${sessionData.courseCode} — ${sessionData.courseTitle} 
         &nbsp;|&nbsp; <span class="text-mono">${sessionData.sessionId}</span> 
         &nbsp;|&nbsp; ${sessionData.examDate}`;

    document.getElementById('verifyWorkspace').style.display = 'block';
    log(`Session started: ${sessionData.sessionId} for ${sessionData.courseCode}`, 'success');
    await startFingerprintLiveCapture();
}

function selectFinger(f, btn) {
    selectedFinger = f;
    document.querySelectorAll('.finger-select-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    log(`Selected finger: ${f}`);
}

function toggleMode() {
    verifyMode = verifyMode === 'fingerprint' ? 'face' : 'fingerprint';
    document.getElementById('fpMode').style.display = verifyMode==='fingerprint' ? 'block' : 'none';
    document.getElementById('faceMode').style.display = verifyMode==='face' ? 'block' : 'none';
    document.getElementById('modeBtn').textContent = verifyMode==='fingerprint' ? 'Switch to Face' : 'Switch to Fingerprint';
    log(`Verification mode: ${verifyMode.toUpperCase()}`);
    if (verifyMode === 'face' && fingerprintReader) {
        liveCaptureStarted = false;
        fingerprintReader.stop().catch(() => {});
    }
    if (verifyMode === 'fingerprint') {
        startFingerprintLiveCapture();
    }
}

async function performFingerprintScan() {
    if (!sessionData.active) { alert('Please start a session first'); return; }
    if (verifyMode === 'face') {
        alert('Switch to Fingerprint mode to scan the fingerprint reader.');
        return;
    }
    if (!readerReady) {
        const connected = await initFingerprintReader();
        if (!connected) return;
    }

    const zone = document.getElementById('fpZone');
    zone.className = 'fingerprint-zone scanning';
    document.getElementById('fpZoneMsg').textContent = 'Scanning ' + selectedFinger + ' finger...';
    log('Initiating ' + selectedFinger + ' scan via DigitalPersona 4500...', 'info');

    try {
        const result = await getFingerprintReader().acquire(Fingerprint.SampleFormat.PngImage, 15000);
        log('Fingerprint sample acquired', 'success');
        zone.className = 'fingerprint-zone ready';
        document.getElementById('fpZoneMsg').textContent = 'Matching against database...';

        const resp = await fetch('../../api/verify.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                courseId: sessionData.courseId,
                finger: selectedFinger,
                sessionId: sessionData.sessionId,
                examDate: sessionData.examDate,
                mode: 'fingerprint',
                liveTemplate: result.sample
            })
        });

        const text = await resp.text();
        if (!resp.ok) throw new Error('Server returned status ' + resp.status);
        if (!text.trim().startsWith('{') && !text.trim().startsWith('[')) {
            throw new Error('Invalid JSON response: ' + text.slice(0, 120));
        }
        handleResult(JSON.parse(text));
    } catch (error) {
        zone.className = 'fingerprint-zone fail';
        document.getElementById('fpZoneMsg').textContent = 'Scan failed. Click to retry.';
        log('Fingerprint scan failed: ' + error.message, 'error');
    }
}

async function triggerScan() {
    if (!sessionData.active) { alert('Please start a session first'); return; }
    const zone = document.getElementById('fpZone');
    zone.className = 'fingerprint-zone scanning';
    document.getElementById('fpZoneMsg').textContent = 'Scanning '+selectedFinger+' finger…';
    log('Initiating '+selectedFinger+' scan via DigitalPersona 4500…', 'info');

    // Simulate scan delay (MINDTCT extraction ~300-600ms)
    await new Promise(r => setTimeout(r, 800 + Math.random()*600));

    log('MINDTCT: Minutiae extraction complete', 'info');
    zone.className = 'fingerprint-zone ready';
    document.getElementById('fpZoneMsg').textContent = 'Matching against database…';

    // Call backend matching API
    const resp = await fetch('../../api/verify.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            courseId:   sessionData.courseId,
            finger:     selectedFinger,
            sessionId:  sessionData.sessionId,
            examDate:   sessionData.examDate,
            // Simulated live template
            liveTemplate: btoa('LIVE_' + selectedFinger + '_' + Date.now())
        })
    });
    const text = await resp.text();
    if (!resp.ok) throw new Error('Server returned status ' + resp.status);
    if (!text.trim().startsWith('{') && !text.trim().startsWith('[')) {
        throw new Error('Invalid JSON response: ' + text.slice(0, 120));
    }
    const data = JSON.parse(text);
    handleResult(data);
}

function handleResult(data) {
    const zone = document.getElementById('fpZone');
    document.getElementById('scoreCard').style.display='block';

    const isFaceMode = verifyMode === 'face';
    const score = isFaceMode ? Math.round(data.score * 100) : data.score;
    const pct   = Math.min(100, score);
    const threshold = isFaceMode ? 40 : <?= FINGERPRINT_MATCH_THRESHOLD ?>;

    document.getElementById('scoreBar').style.width = pct+'%';
    document.getElementById('scoreValue').textContent = score;
    document.getElementById('scoreLabelText').textContent = isFaceMode ? 'Face Match Score' : 'BOZORTH3 Match Score';
    document.getElementById('thresholdDisplay').textContent = 'Threshold: ' + threshold + (isFaceMode ? '% (cosine)' : ' (BOZORTH3)');

    if (data.matched) {
        zone.className = 'fingerprint-zone success';
        currentMatch = data.student;

        document.getElementById('resultWaiting').style.display='none';
        document.getElementById('resultVerified').style.display='block';
        document.getElementById('resultRejected').style.display='none';
        document.getElementById('resultSuccess').style.display='none';

        const initials = (data.student.firstName[0]+data.student.surname[0]).toUpperCase();
        document.getElementById('matchAvatar').textContent = initials;
        document.getElementById('matchName').textContent = data.student.firstName + ' ' + data.student.surname;
        document.getElementById('matchMatric').textContent = data.student.matricNumber;
        document.getElementById('matchMeta').textContent = data.student.department + ' · ' + data.student.level + ' Level';
        document.getElementById('matchEligibility').textContent = data.eligible
            ? '✓ Registered for ' + sessionData.courseCode + ' — eligible to sit'
            : '⚠ Not registered for this course';

        log((isFaceMode ? 'Face' : 'BOZORTH3') + ' Match: '+data.student.surname+', '+data.student.firstName+' ('+data.student.matricNumber+') Score='+score, 'success');
        if (!data.eligible) log('WARNING: Student not registered for '+sessionData.courseCode, 'warn');
    } else {
        zone.className = 'fingerprint-zone fail';

        document.getElementById('resultWaiting').style.display='none';
        document.getElementById('resultVerified').style.display='none';
        document.getElementById('resultRejected').style.display='block';
        document.getElementById('resultSuccess').style.display='none';
        document.getElementById('rejectReason').textContent = data.reason || 'No Match Found';
        document.getElementById('rejectDetail').textContent = 'Score: '+score+' (threshold: ' + threshold + '). Student may be unregistered or impersonator.';

        stats.rejected++;
        document.getElementById('cntRejected').textContent = stats.rejected;
        document.getElementById('cntTotal').textContent = stats.verified + stats.rejected;
        log((isFaceMode ? 'Face' : 'BOZORTH3') + ': No match (score='+score+') — REJECTED', 'error');
    }
}

async function markAttendance(allowed) {
    if (!currentMatch) return;
    const resp = await fetch('../../api/mark_attendance.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            studentId:    currentMatch.id,
            courseId:     sessionData.courseId,
            sessionId:    sessionData.sessionId,
            examDate:     sessionData.examDate,
            fingerUsed:   selectedFinger,
            status:       allowed ? 'present' : 'rejected',
            semester:     'first',
            academicYear: '2024/2025',
            method:       verifyMode
        })
    });
    const data = await resp.json();

    document.getElementById('resultVerified').style.display='none';
    document.getElementById('resultSuccess').style.display='block';
    document.getElementById('successDetail').textContent =
        currentMatch.firstName+' '+currentMatch.surname+' marked present at '+new Date().toLocaleTimeString('en-GB')+' via '+verifyMode;

    stats.verified++;
    document.getElementById('cntVerified').textContent = stats.verified;
    document.getElementById('cntTotal').textContent = stats.verified + stats.rejected;
    log('Attendance marked: '+currentMatch.matricNumber+' PRESENT for '+sessionData.courseCode, 'success');
}

function resetVerify() {
    currentMatch = null;
    document.getElementById('fpZone').className = 'fingerprint-zone';
    document.getElementById('fpZoneMsg').textContent = 'Waiting for fingerprint...';
    document.getElementById('scoreCard').style.display='none';
    document.getElementById('scoreBar').style.width='0%';
    document.getElementById('scoreBar').style.background='linear-gradient(90deg,var(--electric),var(--signal))';
    document.getElementById('resultWaiting').style.display='block';
    document.getElementById('resultVerified').style.display='none';
    document.getElementById('resultRejected').style.display='none';
    document.getElementById('resultSuccess').style.display='none';
    log('Ready for next student');
}

// Face verification
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
    } catch (e) {
        console.error('face-api.js model load failed:', e);
        document.getElementById('faceStatus').className = 'badge badge--danger';
        document.getElementById('faceStatus').textContent = 'Face model load failed';
        alert('Face model load failed. Check network and try again.');
        throw e;
    }
}

async function startCamera() {
    document.getElementById('faceStatus').textContent = 'Loading models…';
    document.getElementById('faceStatus').className   = 'badge badge--amber';
    try {
        await loadFaceApi();
    } catch (e) {
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
            // apply mirror state to preview elements
            applyMirrorToPreview();
        };
    } catch (e) {
        document.getElementById('faceStatus').className   = 'badge badge--danger';
        document.getElementById('faceStatus').textContent = 'Camera failed';
        alert('Face camera error: ' + e.message);
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
            document.getElementById('faceStatus').textContent = 'Face Detected ✓';
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

    document.getElementById('faceStatus').className   = 'badge badge--amber';
    document.getElementById('faceStatus').textContent = 'Detecting face…';
    log('InsightFace: detecting face and computing 512-dim embedding…', 'info');

    try {
        if (!faceApiLoaded) throw new Error('Models not loaded');
        const detection = await faceapi
            .detectSingleFace(canvas, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
            .withFaceLandmarks();

        if (!detection) {
            throw new Error('No face detected. Reposition and try again.');
        }

        if (!(await ensureInsightFaceHealthy())) {
            throw new Error(insightFaceHealth.detail || 'Railway service is unreachable or cold.');
        }

        // Send captured image to server for InsightFace embedding & matching
        const imgB64 = snapshot.split(',')[1];
        document.getElementById('faceStatus').className   = 'badge badge--amber';
        document.getElementById('faceStatus').textContent = 'Sending to InsightFace…';

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), INSIGHTFACE_REQUEST_TIMEOUT_MS);
        const resp = await fetch('../../api/verify.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                courseId:   sessionData.courseId,
                mode:       'face',
                probeImage: imgB64,
                sessionId:  sessionData.sessionId,
                examDate:   sessionData.examDate
            }),
            signal: controller.signal
        });
        clearTimeout(timeoutId);

        const data = await resp.json().catch(() => ({}));
        if (!resp.ok) {
            throw new Error(data.detail || data.reason || ('Server returned status ' + resp.status));
        }
        if (!data.matched) {
            document.getElementById('faceStatus').className   = 'badge badge--danger';
            document.getElementById('faceStatus').textContent = 'Face match failed';
            let detail = data.detail ? '\n' + data.detail : '';
            if (data.reason) alert('Face matching failed: ' + data.reason + detail);
            else alert('Face matching failed' + detail);
            log('Face match failed: ' + (data.reason || 'Unknown reason') + (data.detail ? ' - ' + data.detail : ''), 'error');
        } else {
            document.getElementById('faceStatus').className   = 'badge badge--success';
            document.getElementById('faceStatus').textContent = 'Face matched ✓';
        }
        handleResult(data);
    } catch (e) {
        if (e.name === 'AbortError') {
            e = new Error('InsightFace request timed out after ' + Math.round(INSIGHTFACE_REQUEST_TIMEOUT_MS / 1000) + ' seconds');
        }
        document.getElementById('faceStatus').className   = 'badge badge--danger';
        document.getElementById('faceStatus').textContent = 'Capture failed';
        console.error('Face verification error:', e);
        alert('Face verification failed: ' + e.message);
    } finally {
        if (faceDetectInterval) {
            clearInterval(faceDetectInterval);
            faceDetectInterval = null;
        }
        if (faceStream) {
            faceStream.getTracks().forEach(t => t.stop());
            faceStream = null;
        }
        captureBtn.disabled = false;
    }
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
