<?php
// BEAS — Biometric Verification API
// POST /api/verify.php
// Performs BOZORTH3-style matching against stored templates

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/insightface.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$input         = json_decode(file_get_contents('php://input'), true);
$courseId      = (int)($input['courseId']   ?? 0);
$mode          = $input['mode']            ?? 'fingerprint';
$finger        = $input['finger']          ?? 'thumb';
$liveTemplate  = $input['liveTemplate']    ?? '';
$faceDescriptor= trim($input['faceDescriptor'] ?? '');
$sessionId     = $input['sessionId']       ?? '';

if (!$courseId) {
    echo json_encode(['matched' => false, 'reason' => 'No course selected', 'score' => 0]); exit;
}

$db = getDB();

// -------------------------------------------------------
// MATCHING LOGIC
// In production this calls: python3 /beas/python/fingerprint_match.py
// passing the live template and comparing against all enrolled
// students registered for this course using BOZORTH3.
//
// Here we simulate the pipeline:
// 1. Fetch enrolled students for course
// 2. Compare templates (simulated score)
// 3. Return best match above threshold
// -------------------------------------------------------

// Get registered students for this course with biometric templates
$candidates = [];
if ($mode === 'face') {
    if (empty($faceDescriptor) && empty($input['probeImage'])) {
        echo json_encode(['matched' => false, 'reason' => 'No face descriptor or probe image provided', 'score' => 0]); exit;
    }

    $stmt = $db->prepare("
        SELECT s.id, s.firstName, s.surname, s.matricNumber, s.department, s.level,
               s.profilePictureUrl, s.faceDescriptor as template
        FROM students s
        WHERE s.isActive = 1 AND s.faceDescriptor <> ''
    ");
    $stmt->execute();
    $candidates = $stmt->fetchAll();

    if (empty($candidates)) {
        echo json_encode(['matched' => false, 'reason' => 'No enrolled face descriptors found', 'score' => 0]); exit;
    }
} else {
    $stmt = $db->prepare("
        SELECT s.id, s.firstName, s.surname, s.matricNumber, s.department, s.level,
               s.profilePictureUrl, s.thumbTemplate, s.indexTemplate
        FROM students s
        INNER JOIN course_registrations cr ON cr.studentId = s.id AND cr.courseId = ? AND cr.isActive = 1
        WHERE s.isActive = 1 AND s.fingerprintsCaptured = 1
    ");
    $stmt->execute([$courseId]);
    $candidates = $stmt->fetchAll();

    if (empty($candidates)) {
        $stmt2 = $db->prepare("
            SELECT s.id, s.firstName, s.surname, s.matricNumber, s.department, s.level,
                   s.profilePictureUrl, s.thumbTemplate, s.indexTemplate
            FROM students s
            WHERE s.isActive=1 AND s.fingerprintsCaptured=1
            LIMIT 20
        ");
        $stmt2->execute();
        $candidates = $stmt2->fetchAll();
    }
}

// -------------------------------------------------------
// Simulated matching
// -------------------------------------------------------
$bestScore   = 0;
$bestStudent = null;
$secondScore = 0;

// If a probe image was sent, compute InsightFace embedding via Python
if ($mode === 'face' && !empty($input['probeImage'])) {
    $result = runInsightFaceEmbedding($input['probeImage'], 90);
    if (!$result['ok']) {
        echo json_encode([
            'matched' => false,
            'reason' => 'Embedding error',
            'detail' => $result['detail'] ?? ($result['error'] ?? 'Unknown python error'),
            'score' => 0
        ]);
        exit;
    }

    if (!empty($result['model'])) {
        error_log('[BEAS] insightface model (verify): ' . $result['model'] . ' (session: ' . ($sessionId ?? '') . ')');
    }
    $faceDescriptor = implode(',', array_map(static function ($v) {
        return number_format((float) $v, 6, '.', '');
    }, $result['embedding']));

}

foreach ($candidates as $candidate) {
    if ($mode === 'face') {
        if (empty($candidate['template'])) continue;
        $score = simulateFaceMatch($faceDescriptor, $candidate['template']);
    } else {
        $thumbScore = !empty($candidate['thumbTemplate'])
            ? simulateBozorth3($liveTemplate, $candidate['thumbTemplate'])
            : 0;
        $indexScore = !empty($candidate['indexTemplate'])
            ? simulateBozorth3($liveTemplate, $candidate['indexTemplate'])
            : 0;
        $score = max($thumbScore, $indexScore);
        $candidate['matchedFinger'] = $indexScore > $thumbScore ? 'index' : 'thumb';
    }

    if ($score > $bestScore) {
        $secondScore = $bestScore;
        $bestScore   = $score;
        $bestStudent = $candidate;
    } elseif ($score > $secondScore) {
        $secondScore = $score;
    }
}

// Face: threshold is cosine similarity (0-1), fingerprint: BOZORTH3 score (0-100)
$faceThreshold = 1.0 - FACE_DISTANCE_THRESHOLD; // 0.4 = similarity threshold
$matched = false;

if ($mode === 'face') {
    $threshold = $faceThreshold;
    if ($bestScore >= $threshold && $bestStudent) {
        $matched = true;
    }
} else {
    $threshold = FINGERPRINT_MATCH_THRESHOLD;
    $margin = 8;
    if ($bestScore >= $threshold && $bestStudent && ($bestScore - $secondScore) >= $margin) {
        $matched = true;
    }
}

if ($matched) {
    $eligCheck = $db->prepare("
        SELECT id FROM course_registrations
        WHERE studentId=? AND courseId=? AND isActive=1
    ");
    $eligCheck->execute([$bestStudent['id'], $courseId]);
    $eligible = (bool)$eligCheck->fetch();

    echo json_encode([
        'matched'  => true,
        'eligible' => $eligible,
        'score'    => $bestScore,
        'student'  => [
            'id'          => $bestStudent['id'],
            'firstName'   => $bestStudent['firstName'],
            'surname'     => $bestStudent['surname'],
            'matricNumber'=> $bestStudent['matricNumber'],
            'department'  => $bestStudent['department'],
            'level'       => $bestStudent['level'],
            'photo'       => $bestStudent['profilePictureUrl'],
            'matchedFinger' => $bestStudent['matchedFinger'] ?? null,
        ]
    ]);
} else {
    $thresholdDisplay = $mode === 'face'
        ? number_format($faceThreshold * 100, 1) . '%'
        : (string)$threshold;
    $scoreDisplay = $mode === 'face'
        ? number_format($bestScore * 100, 2) . '%'
        : (string)$bestScore;
    echo json_encode([
        'matched' => false,
        'score'   => $bestScore,
        'reason'  => $bestScore > 0
            ? 'Score ' . $scoreDisplay . ' below threshold ' . $thresholdDisplay
            : 'No enrolled candidates found for this course',
    ]);
}

// -------------------------------------------------------
// Fingerprint template similarity (placeholder for BOZORTH3)
// In production: shell_exec('bozorth3 -p $probe -g $gallery')
// -------------------------------------------------------
function simulateBozorth3(string $probe, string $gallery): float {
    if (empty($probe) || empty($gallery)) return 0;

    if (hash_equals($probe, $gallery)) {
        return 100.0;
    }

    $probeBits = fingerprintAHashBits($probe);
    $galleryBits = fingerprintAHashBits($gallery);
    if ($probeBits !== null && $galleryBits !== null && strlen($probeBits) === strlen($galleryBits)) {
        $distance = fingerprintHammingDistance($probeBits, $galleryBits);
        $total = max(1, strlen($probeBits));
        return max(0, min(100, (1 - ($distance / $total)) * 100));
    }

    // Fallback hash comparison when image decoding is unavailable.
    $probeHash = hash('sha256', $probe, true);
    $galleryHash = hash('sha256', $gallery, true);
    $probeBytes = array_values(unpack('C*', $probeHash));
    $galleryBytes = array_values(unpack('C*', $galleryHash));
    $len = max(count($probeBytes), count($galleryBytes));
    while (count($probeBytes) < $len) $probeBytes[] = 0;
    while (count($galleryBytes) < $len) $galleryBytes[] = 0;
    $probeNorm = normalizeDescriptor(array_map('floatval', $probeBytes));
    $galleryNorm = normalizeDescriptor(array_map('floatval', $galleryBytes));
    $dot = 0;
    for ($i = 0; $i < $len; $i++) {
        $dot += $probeNorm[$i] * $galleryNorm[$i];
    }
    return max(0, min(100, $dot * 100));
}

function fingerprintAHashBits(string $base64Image): ?string {
    $binary = base64_decode(preg_replace('/^data:image\\/[^;]+;base64,/', '', $base64Image), true);
    if ($binary === false) {
        return null;
    }

    $image = @imagecreatefromstring($binary);
    if (!$image) {
        return null;
    }

    $width = 16;
    $height = 16;
    $scaled = imagecreatetruecolor($width, $height);
    imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
    imagefilter($scaled, IMG_FILTER_GRAYSCALE);

    $values = [];
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($scaled, $x, $y);
            $gray = $rgb & 0xFF;
            $values[] = $gray;
        }
    }

    $avg = array_sum($values) / max(1, count($values));
    $bits = '';
    foreach ($values as $value) {
        $bits .= $value >= $avg ? '1' : '0';
    }



    return $bits;
}

function fingerprintHammingDistance(string $a, string $b): int {
    $len = min(strlen($a), strlen($b));
    $distance = 0;
    for ($i = 0; $i < $len; $i++) {
        if ($a[$i] !== $b[$i]) {
            $distance++;
        }
    }
    return $distance + abs(strlen($a) - strlen($b));
}

function parseFaceDescriptor(string $descriptor): array {
    $parts = array_filter(array_map('trim', explode(',', $descriptor)), fn($v) => $v !== '');
    return array_values(array_map('floatval', $parts));
}

function normalizeDescriptor(array $vector): array {
    $norm = 0.0;
    foreach ($vector as $value) {
        $norm += $value * $value;
    }
    $norm = sqrt($norm);
    if ($norm === 0.0) {
        return array_values($vector);
    }
    return array_values(array_map(fn($value) => $value / $norm, $vector));
}

function cosineSimilarity(array $a, array $b): float {
    $count = min(count($a), count($b));
    if ($count === 0) {
        return 0.0;
    }
    $dot = 0.0;
    for ($i = 0; $i < $count; $i++) {
        $dot += $a[$i] * $b[$i];
    }
    return $dot;
}

function simulateFaceMatch(string $probe, string $gallery): float {
    $probeArr   = parseFaceDescriptor($probe);
    $galleryArr = parseFaceDescriptor($gallery);
    if (empty($probeArr) || empty($galleryArr)) {
        return 0;
    }

    $probeNorm   = normalizeDescriptor($probeArr);
    $galleryNorm = normalizeDescriptor($galleryArr);
    $similarity  = cosineSimilarity($probeNorm, $galleryNorm);
    return max(0, min(1, $similarity));
}
?>
