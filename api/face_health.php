<?php
// BEAS â€” InsightFace / Railway health probe
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/insightface.php';
requireLogin();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$result = probeInsightFaceHealth(defined('INSIGHTFACE_HEALTH_TIMEOUT') ? (int) INSIGHTFACE_HEALTH_TIMEOUT : 5);

if (!empty($result['healthy'])) {
    jsonResponse([
        'ok' => true,
        'healthy' => true,
        'status' => $result['status'] ?? 200,
        'detail' => $result['detail'] ?? 'healthy',
    ]);
}

jsonResponse([
    'ok' => false,
    'healthy' => false,
    'error' => $result['error'] ?? 'health_probe_failed',
    'detail' => $result['detail'] ?? 'Railway service is unavailable',
    'status' => $result['status'] ?? 0,
], 503);
