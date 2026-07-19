<?php
/**
 * BEAS — One-Click Installer (fixed)
 * Visit: http://localhost/beas/setup.php
 * DELETE THIS FILE after setup is complete.
 */

$step = $_POST['step'] ?? 'welcome';
$log  = [];

function ok(string $msg):  void { global $log; $log[] = ['msg'=>$msg,'ok'=>true]; }
function fail(string $msg): void { global $log; $log[] = ['msg'=>$msg,'ok'=>false]; }

// ── Every DDL and seed statement as a plain array ─────────────────────────
// No splitting, no file parsing — each string is one complete statement.
function getStatements(string $dbName): array {
    return [
        // Tables
        "CREATE TABLE IF NOT EXISTS users (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            username    VARCHAR(50)  NOT NULL UNIQUE,
            email       VARCHAR(100) NOT NULL UNIQUE,
            password    VARCHAR(255) NOT NULL,
            full_name   VARCHAR(100),
            role        ENUM('superadmin','admin','invigilator') DEFAULT 'admin',
            isActive    BOOLEAN   DEFAULT TRUE,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS students (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            surname             VARCHAR(50)  NOT NULL,
            firstName           VARCHAR(50)  NOT NULL,
            middleName          VARCHAR(50),
            age                 INT,
            phoneNumber         VARCHAR(20),
            email               VARCHAR(100) UNIQUE,
            department          VARCHAR(100),
            course              VARCHAR(100),
            level               VARCHAR(10),
            matricNumber        VARCHAR(30)  NOT NULL UNIQUE,
            thumbTemplate       TEXT,
            indexTemplate       TEXT,
            middleTemplate      TEXT,
            ringTemplate        TEXT,
            pinkyTemplate       TEXT,
            fingerprintsCaptured BOOLEAN DEFAULT FALSE,
            profilePictureUrl   VARCHAR(255),
            faceDescriptor      TEXT,
            password            VARCHAR(255),
            isActive            BOOLEAN   DEFAULT TRUE,
            enrolled_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS courses (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            courseCode  VARCHAR(20)  NOT NULL UNIQUE,
            courseTitle VARCHAR(150) NOT NULL,
            courseUnit  INT          NOT NULL DEFAULT 2,
            level       INT          NOT NULL,
            semester    ENUM('first','second') NOT NULL,
            isActive    BOOLEAN   DEFAULT TRUE,
            createdAt   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            createdBy   INT,
            FOREIGN KEY (createdBy) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS course_registrations (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            matricNumber VARCHAR(30) NOT NULL,
            courseCode   VARCHAR(20) NOT NULL,
            courseTitle  VARCHAR(150),
            courseUnit   INT,
            level        INT,
            semester     ENUM('first','second'),
            status       BOOLEAN DEFAULT TRUE,
            approvedAt   TIMESTAMP NULL,
            approvedBy   INT NULL,
            isActive     BOOLEAN   DEFAULT TRUE,
            registeredAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            studentId    INT,
            courseId     INT,
            FOREIGN KEY (studentId) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (courseId)  REFERENCES courses(id)  ON DELETE CASCADE,
            UNIQUE KEY unique_registration (matricNumber, courseCode, semester)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS attendance (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            studentId          INT          NOT NULL,
            matricNumber       VARCHAR(30)  NOT NULL,
            courseId           INT          NOT NULL,
            courseCode         VARCHAR(20),
            courseTitle        VARCHAR(150),
            attendanceDate     DATE         NOT NULL,
            signInTime         DATETIME,
            signOutTime        DATETIME,
            signInFingerUsed   VARCHAR(20),
            signOutFingerUsed  VARCHAR(20),
            signInStatus       ENUM('present','rejected','pending') DEFAULT 'pending',
            signOutStatus      ENUM('signed_out','not_signed_out')  DEFAULT 'not_signed_out',
            totalDuration      VARCHAR(20),
            semester           ENUM('first','second'),
            academicYear       VARCHAR(20),
            sessionId          VARCHAR(50),
            isActive           BOOLEAN DEFAULT TRUE,
            verificationMethod ENUM('fingerprint','face','both') DEFAULT 'fingerprint',
            FOREIGN KEY (studentId) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (courseId)  REFERENCES courses(id)  ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS exam_sessions (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            sessionId     VARCHAR(50)  NOT NULL UNIQUE,
            courseId      INT          NOT NULL,
            courseCode    VARCHAR(20),
            courseTitle   VARCHAR(150),
            examDate      DATE         NOT NULL,
            startTime     TIME,
            endTime       TIME,
            venue         VARCHAR(100),
            semester      ENUM('first','second'),
            academicYear  VARCHAR(20),
            invigilatorId INT,
            status        ENUM('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
            totalExpected INT DEFAULT 0,
            totalVerified INT DEFAULT 0,
            createdAt     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (courseId)      REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (invigilatorId) REFERENCES users(id)   ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Indexes — use IF NOT EXISTS syntax (MySQL 8+) or ignore duplicate key errors
        "CREATE INDEX IF NOT EXISTS idx_students_matric       ON students(matricNumber)",
        "CREATE INDEX IF NOT EXISTS idx_attendance_date       ON attendance(attendanceDate)",
        "CREATE INDEX IF NOT EXISTS idx_attendance_course     ON attendance(courseId)",
        "CREATE INDEX IF NOT EXISTS idx_registrations_matric  ON course_registrations(matricNumber)",
        "CREATE INDEX IF NOT EXISTS idx_registrations_course  ON course_registrations(courseCode)",
    ];
}

// ── Process install form ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'install') {
    $host      = trim($_POST['db_host']         ?? 'localhost');
    $port      = trim($_POST['db_port']         ?? '3306');
    $user      = trim($_POST['db_user']         ?? 'root');
    $pass      =      $_POST['db_pass']         ?? '';
    $name      = trim($_POST['db_name']         ?? 'beas_db');
    $adminUser = trim($_POST['admin_username']   ?? 'admin');
    $adminPass =      $_POST['admin_password']   ?? '';
    $adminEmail= trim($_POST['admin_email']      ?? 'admin@fuoye.edu.ng');
    $adminName = trim($_POST['admin_fullname']   ?? 'System Administrator');

    // 1. Connect WITHOUT a database selected first
    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        ok("Connected to MySQL server at {$host}:{$port}");
    } catch (PDOException $e) {
        fail("Cannot connect to MySQL: " . $e->getMessage());
        $step = 'error';
        goto render;
    }

    // 2. Create / select database
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");
        ok("Database `{$name}` ready");
    } catch (PDOException $e) {
        fail("Could not create database: " . $e->getMessage());
        $step = 'error';
        goto render;
    }

    // 3. Create tables one by one
    $tableOk = true;
    foreach (getStatements($name) as $sql) {
        $label = preg_match('/CREATE\s+(TABLE|INDEX)\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $sql, $m)
               ? strtoupper($m[1]) . ' ' . $m[2]
               : substr(trim($sql), 0, 60);
        try {
            $pdo->exec($sql);
            ok("Created: {$label}");
        } catch (PDOException $e) {
            // Duplicate index is fine on re-run (MySQL < 8.0 ignores IF NOT EXISTS for indexes)
            if ($e->getCode() === '42000' && str_contains($e->getMessage(), 'Duplicate key name')) {
                ok("Index already exists (skipped): {$label}");
            } else {
                fail("Failed [{$label}]: " . $e->getMessage());
                $tableOk = false;
            }
        }
    }

    if (!$tableOk) {
        $step = 'error';
        goto render;
    }

    // 4. Admin user
    try {
        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $pdo->prepare(
            "INSERT INTO users (username, email, password, full_name, role)
             VALUES (?, ?, ?, ?, 'superadmin')
             ON DUPLICATE KEY UPDATE password=VALUES(password), full_name=VALUES(full_name)"
        )->execute([$adminUser, $adminEmail, $hash, $adminName]);
        ok("Admin user '{$adminUser}' saved");
    } catch (PDOException $e) {
        fail("Admin user error: " . $e->getMessage());
        $step = 'error';
        goto render;
    }

    // 5. Sample courses
    try {
        $adminId = (int)$pdo->lastInsertId() ?: 1;
        $courses = [
            ['CPE 501','Digital Signal Processing',     3,500,'first'],
            ['CPE 503','Computer Networks',             3,500,'first'],
            ['CPE 505','Embedded Systems Design',       3,500,'first'],
            ['CPE 507','Artificial Intelligence',       3,500,'first'],
            ['CPE 511','Final Year Project I',          6,500,'first'],
            ['CPE 401','Microprocessor Systems',        3,400,'first'],
            ['CPE 403','Control Systems',               3,400,'first'],
            ['CPE 301','Digital Electronics',           3,300,'first'],
            ['CPE 201','Introduction to Programming',   3,200,'first'],
        ];
        $ins = $pdo->prepare(
            "INSERT IGNORE INTO courses (courseCode,courseTitle,courseUnit,level,semester,createdBy)
             VALUES (?,?,?,?,?,1)"
        );
        foreach ($courses as $c) $ins->execute($c);
        ok(count($courses) . " sample courses inserted");
    } catch (PDOException $e) {
        fail("Courses seed error: " . $e->getMessage());
    }

    // 6. Sample students with mock biometric templates
    try {
        $students = [
            ['Adeyemi','Tunde',  'Olawale','FUO/19/ENG/CPE/001','500','Computer Engineering'],
            ['Okonkwo','Chioma', 'Amaka',  'FUO/19/ENG/CPE/002','500','Computer Engineering'],
            ['Ibrahim','Fatima', 'Zainab', 'FUO/20/ENG/CPE/001','400','Computer Engineering'],
            ['Eze',    'David',  'Chukwu', 'FUO/20/ENG/CPE/002','400','Computer Engineering'],
            ['Bello',  'Amina',  'Laila',  'FUO/21/ENG/CPE/001','300','Computer Engineering'],
            ['Afolabi','Seun',   'Adeola', 'FUO/21/ENG/CPE/002','300','Computer Engineering'],
        ];
        $ins = $pdo->prepare(
            "INSERT IGNORE INTO students
             (surname,firstName,middleName,matricNumber,level,department,course,
              fingerprintsCaptured,thumbTemplate,indexTemplate,middleTemplate,ringTemplate,pinkyTemplate)
             VALUES (?,?,?,?,?,?,?,1,?,?,?,?,?)"
        );
        foreach ($students as $s) {
            $mk = fn(string $type) => base64_encode(
                "NBIS_{$type}_" . strtoupper($s[3]) . '_' . bin2hex(random_bytes(6))
            );
            $ins->execute([
                $s[0],$s[1],$s[2],$s[3],$s[4],$s[5],'B.Eng',
                $mk('THUMB'),$mk('INDEX'),$mk('MIDDLE'),$mk('RING'),$mk('PINKY')
            ]);
        }
        ok(count($students) . " sample students inserted");
    } catch (PDOException $e) {
        fail("Students seed error: " . $e->getMessage());
    }

    // 7. Register sample students to matching-level courses
    try {
        $pdo->exec(
            "INSERT IGNORE INTO course_registrations
               (matricNumber,courseCode,courseTitle,courseUnit,level,semester,studentId,courseId,approvedAt,approvedBy,isActive)
             SELECT s.matricNumber, c.courseCode, c.courseTitle, c.courseUnit,
                    c.level, c.semester, s.id, c.id, NOW(), 1, 1
             FROM   students s
             JOIN   courses  c ON c.level = s.level AND c.isActive = 1
             WHERE  s.isActive = 1"
        );
        ok("Course registrations created for all sample students");
    } catch (PDOException $e) {
        fail("Registration seed error: " . $e->getMessage());
    }

    // 8. Write config.php
    $appUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/beas';
    $cfg = <<<PHP
<?php
define('DB_HOST', '$host');
define('DB_USER', '$user');
define('DB_PASS', '$pass');
define('DB_NAME', '$name');
define('DB_PORT', '$port');

define('APP_NAME',        'BEAS');
define('APP_FULL_NAME',   'Biometric Examination Authentication System');
define('APP_INSTITUTION', 'Federal University Oye-Ekiti');
define('APP_DEPARTMENT',  'Department of Computer Engineering');
define('APP_VERSION',     '1.0.0');
define('APP_URL',         '$appUrl');

define('SESSION_LIFETIME',            3600);
define('FINGERPRINT_MATCH_THRESHOLD', 40);
define('FACE_DISTANCE_THRESHOLD',     0.6);

define('UPLOAD_PATH',    __DIR__ . '/../uploads/');
define('PROFILE_PATH',   __DIR__ . '/../uploads/profiles/');
define('BIOMETRIC_PATH', __DIR__ . '/../uploads/biometric/');
define('PYTHON_BIN',     'python3');
define('NBIS_SCRIPT',    __DIR__ . '/../python/fingerprint_match.py');

function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        try {
            \$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException \$e) {
            die(json_encode(['error' => 'Database connection failed: ' . \$e->getMessage()]));
        }
    }
    return \$pdo;
}
PHP;

    if (file_put_contents(__DIR__ . '/config/config.php', $cfg) !== false) {
        ok("config/config.php written with your settings");
    } else {
        fail("Could not write config/config.php — check folder permissions");
    }

    // 9. Create upload directories
    foreach (['uploads', 'uploads/profiles', 'uploads/biometric'] as $dir) {
        $path = __DIR__ . '/' . $dir;
        if (!is_dir($path)) mkdir($path, 0755, true);
    }
    ok("Upload directories created");

    $step = 'done';
}

render:
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>BEAS Setup</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Inter:wght@400;500&family=DM+Mono&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#0A0C12;color:#E8EAF2;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.setup{width:100%;max-width:540px;background:#111420;border:1px solid #252A42;border-radius:16px;overflow:hidden}
.setup-head{padding:26px 32px;border-bottom:1px solid #252A42;background:linear-gradient(135deg,rgba(30,111,255,.1),transparent)}
.setup-title{font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:700}
.setup-sub{font-size:.78rem;color:#8B90A8;margin-top:3px}
.setup-body{padding:28px 32px}
.section{font-size:.68rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#555A72;margin:20px 0 10px}
.section:first-child{margin-top:0}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.fg{margin-bottom:13px}
label{display:block;font-size:.74rem;font-weight:500;color:#8B90A8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
input{width:100%;background:#181C2E;border:1px solid #252A42;color:#E8EAF2;padding:9px 13px;border-radius:7px;font-size:.875rem;font-family:'Inter',sans-serif;transition:border-color .15s}
input:focus{outline:none;border-color:#1E6FFF;box-shadow:0 0 0 3px rgba(30,111,255,.18)}
input::placeholder{color:#555A72}
.hint{font-size:.7rem;color:#555A72;margin-top:4px}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 20px;background:#1E6FFF;color:#fff;border:none;border-radius:7px;font-size:.9rem;font-weight:500;cursor:pointer;width:100%;margin-top:6px;font-family:'Inter',sans-serif;transition:background .15s}
.btn:hover{background:#1458CC}
.btn-ghost{background:#1E2236;border:1px solid #252A42;color:#8B90A8}
.btn-ghost:hover{background:#252A42;color:#E8EAF2}
.divider{height:1px;background:#252A42;margin:20px 0}
.log{display:flex;flex-direction:column;gap:5px;max-height:320px;overflow-y:auto;margin-bottom:20px;padding-right:4px}
.log-item{display:flex;align-items:flex-start;gap:9px;font-size:.78rem;font-family:'DM Mono',monospace;line-height:1.5}
.log-item.ok  .icon{color:#00C896}.log-item.ok  .txt{color:#8B90A8}
.log-item.fail .icon{color:#FF4B55}.log-item.fail .txt{color:#FF4B55}
.icon{flex-shrink:0;font-style:normal}
.success-box{background:rgba(0,200,150,.07);border:1px solid rgba(0,200,150,.25);border-radius:10px;padding:24px;text-align:center}
.success-box h2{font-family:'Space Grotesk',sans-serif;color:#00C896;font-size:1.2rem;margin-bottom:6px}
.success-box p{font-size:.83rem;color:#8B90A8;line-height:1.6}
.link-btn{display:inline-block;margin-top:16px;padding:10px 28px;background:#1E6FFF;color:#fff;text-decoration:none;border-radius:7px;font-size:.88rem;font-weight:500}
.link-btn:hover{background:#1458CC}
.warn{font-size:.73rem;color:#F5A623;background:rgba(245,166,35,.08);border:1px solid rgba(245,166,35,.2);border-radius:6px;padding:8px 12px;margin-top:14px;line-height:1.5}
.error-box{background:rgba(255,75,85,.07);border:1px solid rgba(255,75,85,.25);border-radius:10px;padding:18px;margin-top:4px}
.error-box p{font-size:.83rem;color:#FF4B55;line-height:1.6}
</style>
</head>
<body>
<div class="setup">
  <div class="setup-head">
    <div class="setup-title">⬡ BEAS Installer</div>
    <div class="setup-sub">Biometric Examination Authentication System — FUOYE</div>
  </div>
  <div class="setup-body">

  <?php if ($step === 'welcome' || $step === 'install'): ?>
  <form method="POST">
    <input type="hidden" name="step" value="install">

    <div class="section">Database Configuration</div>
    <div class="grid2">
      <div class="fg"><label>Host</label><input name="db_host" value="<?= htmlspecialchars($_POST['db_host']??'localhost') ?>" required placeholder="localhost"></div>
      <div class="fg"><label>Port</label><input name="db_port" value="<?= htmlspecialchars($_POST['db_port']??'3306') ?>"     required placeholder="3306"></div>
    </div>
    <div class="fg"><label>MySQL Username</label><input name="db_user" value="<?= htmlspecialchars($_POST['db_user']??'root') ?>" required></div>
    <div class="fg"><label>MySQL Password</label><input type="password" name="db_pass" placeholder="Leave blank if none"></div>
    <div class="fg"><label>Database Name</label><input name="db_name" value="<?= htmlspecialchars($_POST['db_name']??'beas_db') ?>" required>
      <div class="hint">Will be created automatically if it doesn't exist</div>
    </div>

    <div class="divider"></div>

    <div class="section">Admin Account</div>
    <div class="fg"><label>Full Name</label><input name="admin_fullname" value="<?= htmlspecialchars($_POST['admin_fullname']??'System Administrator') ?>" required></div>
    <div class="grid2">
      <div class="fg"><label>Username</label><input name="admin_username" value="<?= htmlspecialchars($_POST['admin_username']??'admin') ?>" required></div>
      <div class="fg"><label>Email</label><input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email']??'admin@fuoye.edu.ng') ?>" required></div>
    </div>
    <div class="fg"><label>Password</label><input type="password" name="admin_password" placeholder="Minimum 8 characters" required minlength="8">
      <div class="hint">You'll use this to log into BEAS</div>
    </div>

    <button type="submit" class="btn">Install BEAS →</button>
  </form>

  <?php elseif ($step === 'done'): ?>
  <div class="log">
    <?php foreach ($log as $l): ?>
    <div class="log-item <?= $l['ok']?'ok':'fail' ?>">
      <i class="icon"><?= $l['ok']?'✓':'✗' ?></i>
      <span class="txt"><?= htmlspecialchars($l['msg']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="success-box">
    <h2>Installation Complete</h2>
    <p>All tables created, sample data inserted, and admin account ready.<br>
       You can now log in with your credentials.</p>
    <a href="index.php" class="link-btn">Go to Login →</a>
  </div>
  <div class="warn">⚠ <strong>Security:</strong> Delete <code>setup.php</code> from your server before going live.</div>

  <?php elseif ($step === 'error'): ?>
  <div class="log">
    <?php foreach ($log as $l): ?>
    <div class="log-item <?= $l['ok']?'ok':'fail' ?>">
      <i class="icon"><?= $l['ok']?'✓':'✗' ?></i>
      <span class="txt"><?= htmlspecialchars($l['msg']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="error-box">
    <p>Setup encountered an error. Review the log above, check your MySQL credentials and server status, then try again.</p>
  </div>
  <form method="POST" style="margin-top:16px">
    <input type="hidden" name="step" value="welcome">
    <button type="submit" class="btn btn-ghost">← Try Again</button>
  </form>
  <?php endif; ?>

  </div>
</div>
</body>
</html>
