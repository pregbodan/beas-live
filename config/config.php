<?php
// BEAS - Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'mysql.tikearn.org.ng');
define('DB_USER', getenv('DB_USER') ?: 'tikearno_pregbodan');
define('DB_PASS', getenv('DB_PASS') ?: 'AdebomiAdebomi@1');
define('DB_NAME', getenv('DB_NAME') ?: 'tikearno_beas');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

define('APP_NAME', 'BEAS');
define('APP_FULL_NAME', 'Biometric Examination Authentication System');
define('APP_INSTITUTION', 'Federal University Oye-Ekiti');
define('APP_DEPARTMENT', 'Department of Computer Engineering');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'https://tikearn.org.ng/beas');

define('SESSION_LIFETIME', 3600);

define('FINGERPRINT_MATCH_THRESHOLD', 60);
define('FACE_DISTANCE_THRESHOLD', 0.6);

define('FINGERPRINT_READER_HOST', getenv('FINGERPRINT_READER_HOST') ?: '127.0.0.1');
define('FINGERPRINT_READER_PORT', (int)(getenv('FINGERPRINT_READER_PORT') ?: 52181));
define('FINGERPRINT_READER_PROTOCOL', getenv('FINGERPRINT_READER_PROTOCOL') ?: 'ws');
define('FINGERPRINT_READER_CLIENT_PATH', getenv('FINGERPRINT_READER_CLIENT_PATH') ?: 'dpfpcapture');

define('UPLOAD_PATH', __DIR__ . '/../../uploads/');
define('PROFILE_PATH', __DIR__ . '/../../uploads/profiles/');
define('BIOMETRIC_PATH', __DIR__ . '/../../uploads/biometric/');

// Render InsightFace service. Add these to the live cPanel config/config.php.
define('INSIGHTFACE_RENDER_URL', getenv('INSIGHTFACE_RENDER_URL') ?: 'https://beas-insightface-production.up.railway.app');
define('INSIGHTFACE_RENDER_API_KEY', getenv('INSIGHTFACE_RENDER_API_KEY') ?: 'jmer_6-_16_BLc3n4shKEFNuQa72ZdBB');
define('INSIGHTFACE_RENDER_TIMEOUT', (int)(getenv('INSIGHTFACE_RENDER_TIMEOUT') ?: 200));
define('INSIGHTFACE_RENDER_CONNECT_TIMEOUT', (int)(getenv('INSIGHTFACE_RENDER_CONNECT_TIMEOUT') ?: 15));
define('INSIGHTFACE_RENDER_RETRIES', max(0, (int)(getenv('INSIGHTFACE_RENDER_RETRIES') ?: 2)));
define('INSIGHTFACE_HEALTH_TIMEOUT', max(1, (int)(getenv('INSIGHTFACE_HEALTH_TIMEOUT') ?: 15)));
define('INSIGHTFACE_HEALTH_CONNECT_TIMEOUT', max(1, (int)(getenv('INSIGHTFACE_HEALTH_CONNECT_TIMEOUT') ?: 10)));
define(
    'INSIGHTFACE_CLIENT_TIMEOUT_MS',
    (int)(getenv('INSIGHTFACE_CLIENT_TIMEOUT_MS') ?: max(90000, min(240000, ((int) INSIGHTFACE_RENDER_TIMEOUT + 20) * 1000)))
);

// cPanel Python path for face embedding
define('PYTHON_BIN', getenv('PYTHON_BIN') ?: '/opt/alt/python312/bin/python3.12 -u');
define('NBIS_SCRIPT', __DIR__ . '/../../python/fingerprint_match.py');
define('INSIGHTFACE_MODEL_ROOT', getenv('INSIGHTFACE_MODEL_ROOT') ?: '/home/tikearno/public_html/beas/insightface');
define('INSIGHTFACE_DEBUG', filter_var(getenv('INSIGHTFACE_DEBUG') ?: false, FILTER_VALIDATE_BOOL));

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

