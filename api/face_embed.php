<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/insightface.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$imgB64 = $input['probeImage'] ?? $input['image'] ?? '';
if (!$imgB64) jsonResponse(['error' => 'No image provided'], 400);

$result = runInsightFaceEmbedding($imgB64, 90);
if ($result['ok']) {
    if (!empty($result['model'])) {
        error_log('[BEAS] insightface model (face_embed): ' . $result['model']);
    }
    jsonResponse([
        'embedding' => $result['embedding'],
        'model' => $result['model'] ?? null,
    ]);
}

 $errorPayload = [
    'error' => $result['error'] ?? 'embedding_failed',
    'detail' => $result['detail'] ?? 'Unknown insightface error',
];

if (INSIGHTFACE_DEBUG) {
    $errorPayload['debug'] = [
        'raw' => $result['raw'] ?? null,
        'stdout' => $result['stdout'] ?? null,
        'stderr' => $result['stderr'] ?? null,
        'model_root' => defined('INSIGHTFACE_MODEL_ROOT') ? INSIGHTFACE_MODEL_ROOT : null,
    ];
}

jsonResponse($errorPayload, 500);

?>
