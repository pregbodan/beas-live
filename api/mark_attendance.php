<?php
// BEAS — Mark Attendance API
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$input       = json_decode(file_get_contents('php://input'), true);
$studentId   = (int)($input['studentId']   ?? 0);
$courseId    = (int)($input['courseId']    ?? 0);
$sessionId   = $input['sessionId']         ?? '';
$examDate    = $input['examDate']          ?? date('Y-m-d');
$fingerUsed  = $input['fingerUsed']        ?? 'thumb';
$status      = $input['status']            ?? 'present';
$semester    = $input['semester']          ?? 'first';
$academicYear= $input['academicYear']      ?? '2024/2025';
$method      = $input['method']            ?? 'fingerprint';

if (!$studentId || !$courseId) {
    echo json_encode(['success' => false, 'error' => 'Missing studentId or courseId']); exit;
}

$db = getDB();

// Prevent duplicate sign-in for same student/course/date
$check = $db->prepare("SELECT id, signInTime FROM attendance WHERE studentId=? AND courseId=? AND attendanceDate=?");
$check->execute([$studentId, $courseId, $examDate]);
$existing = $check->fetch();

if ($existing) {
    // Already signed in — update sign-out
    $db->prepare("UPDATE attendance SET signOutTime=NOW(), signOutStatus='signed_out', signOutFingerUsed=? WHERE id=?")
       ->execute([$fingerUsed, $existing['id']]);

    $duration = round((time() - strtotime($existing['signInTime'])) / 60) . ' min';
    $db->prepare("UPDATE attendance SET totalDuration=? WHERE id=?")->execute([$duration, $existing['id']]);

    echo json_encode(['success' => true, 'action' => 'signed_out', 'duration' => $duration]); exit;
}

// Fetch course info
$course = $db->prepare("SELECT courseCode, courseTitle FROM courses WHERE id=?");
$course->execute([$courseId]);
$course = $course->fetch();

// Insert new attendance record
$stmt = $db->prepare("
    INSERT INTO attendance
    (studentId, matricNumber, courseId, courseCode, courseTitle,
     attendanceDate, signInTime, signInFingerUsed, signInStatus,
     semester, academicYear, sessionId, verificationMethod)
    SELECT ?, s.matricNumber, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?
    FROM students s WHERE s.id=?
");
$stmt->execute([
    $studentId, $courseId,
    $course['courseCode'] ?? '', $course['courseTitle'] ?? '',
    $examDate, $fingerUsed, $status,
    $semester, $academicYear, $sessionId, $method,
    $studentId
]);

echo json_encode([
    'success'     => true,
    'action'      => 'signed_in',
    'attendanceId'=> $db->lastInsertId(),
    'timestamp'   => date('H:i:s'),
]);
?>
