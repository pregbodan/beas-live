<?php
// Run from cPanel cron or manually after deploying config.snippet.php constants.
// Example:
// /opt/alt/php82/usr/bin/php /home/tikearno/public_html/beas/tools/sync_face_embeddings_to_render.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/insightface.php';

$db = getDB();
$stmt = $db->query("
    SELECT id, faceDescriptor
    FROM students
    WHERE isActive = 1
      AND faceDescriptor IS NOT NULL
      AND faceDescriptor <> ''
");

$embeddings = [];
foreach ($stmt->fetchAll() as $row) {
    $vector = array_values(array_filter(array_map('trim', explode(',', $row['faceDescriptor'])), static function ($value) {
        return $value !== '';
    }));
    $vector = array_map('floatval', $vector);
    if (!empty($vector)) {
        $embeddings[] = [
            'studentId' => (string) $row['id'],
            'embedding' => $vector,
        ];
    }
}

$baseUrl = defined('INSIGHTFACE_RENDER_URL') ? trim((string) INSIGHTFACE_RENDER_URL) : '';
if ($baseUrl === '' || strpos($baseUrl, 'your-render-service') !== false) {
    fwrite(STDERR, "INSIGHTFACE_RENDER_URL is not configured.\n");
    exit(1);
}

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
];
if (defined('INSIGHTFACE_RENDER_API_KEY') && INSIGHTFACE_RENDER_API_KEY !== '') {
    $headers[] = 'X-BEAS-API-Key: ' . INSIGHTFACE_RENDER_API_KEY;
}

$payload = json_encode(['embeddings' => $embeddings], JSON_UNESCAPED_SLASHES);
$response = renderInsightFacePost(rtrim($baseUrl, '/') . '/sync', $payload, $headers, defined('INSIGHTFACE_RENDER_TIMEOUT') ? (int) INSIGHTFACE_RENDER_TIMEOUT : 90);

if (!$response['ok']) {
    fwrite(STDERR, "Sync failed: " . ($response['detail'] ?? $response['error'] ?? 'unknown error') . "\n");
    exit(1);
}

echo "Synced " . count($embeddings) . " face embeddings to Render.\n";

