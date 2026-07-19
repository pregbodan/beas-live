<?php

function runInsightFaceEmbedding(string $imgB64, int $timeoutSeconds = 90): array
{
    $imgB64 = trim($imgB64);
    if ($imgB64 === '') {
        return ['ok' => false, 'error' => 'No image provided'];
    }

    $baseUrl = defined('INSIGHTFACE_RENDER_URL') ? trim((string) INSIGHTFACE_RENDER_URL) : '';
    if ($baseUrl === '' || strpos($baseUrl, 'your-render-service') !== false) {
        return [
            'ok' => false,
            'error' => 'render_not_configured',
            'detail' => 'Set INSIGHTFACE_RENDER_URL in cPanel config/config.php.',
        ];
    }

    $timeout = defined('INSIGHTFACE_RENDER_TIMEOUT') ? (int) INSIGHTFACE_RENDER_TIMEOUT : $timeoutSeconds;
    $timeout = max(90, $timeout);
    $connectTimeout = defined('INSIGHTFACE_RENDER_CONNECT_TIMEOUT') ? (int) INSIGHTFACE_RENDER_CONNECT_TIMEOUT : max(30, (int) ceil($timeout / 4));
    $connectTimeout = max(10, min($connectTimeout, $timeout));
    $retries = defined('INSIGHTFACE_RENDER_RETRIES') ? (int) INSIGHTFACE_RENDER_RETRIES : 1;
    $retries = max(0, $retries);

    $endpoint = rtrim($baseUrl, '/') . '/embed';
    $payload = json_encode(['probeImage' => $imgB64], JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'json_encode_failed', 'detail' => json_last_error_msg()];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if (defined('INSIGHTFACE_RENDER_API_KEY') && INSIGHTFACE_RENDER_API_KEY !== '') {
        $headers[] = 'X-BEAS-API-Key: ' . INSIGHTFACE_RENDER_API_KEY;
    }

    $response = renderInsightFacePost($endpoint, $payload, $headers, max(5, $timeout), $connectTimeout, $retries);
    if (!$response['ok'] && insightfaceResponseLooksLikeTimeout($response)) {
        $retryTimeout = min(180, max(120, $timeout));
        $retryConnectTimeout = min($retryTimeout, max($connectTimeout, 45));
        $response = renderInsightFacePost($endpoint, $payload, $headers, $retryTimeout, $retryConnectTimeout, 0);
    }

    if (!$response['ok']) {
        return $response;
    }

    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'invalid_render_response',
            'detail' => 'Render returned non-JSON response',
            'raw' => $response['body'],
        ];
    }

    if (!empty($decoded['embedding']) && is_array($decoded['embedding'])) {
        return [
            'ok' => true,
            'embedding' => $decoded['embedding'],
            'model' => $decoded['model'] ?? 'render-insightface',
            'raw' => $decoded,
            'stdout' => $response['body'],
            'stderr' => '',
        ];
    }

    return [
        'ok' => false,
        'error' => $decoded['error'] ?? 'render_embedding_failed',
        'detail' => $decoded['detail'] ?? $decoded['message'] ?? 'Render did not return an embedding',
        'raw' => $decoded,
        'stdout' => $response['body'],
        'stderr' => '',
    ];
}

function probeInsightFaceHealth(int $timeoutSeconds = 5): array
{
    $baseUrl = defined('INSIGHTFACE_RENDER_URL') ? trim((string) INSIGHTFACE_RENDER_URL) : '';
    if ($baseUrl === '' || strpos($baseUrl, 'your-render-service') !== false) {
        return [
            'ok' => false,
            'healthy' => false,
            'error' => 'render_not_configured',
            'detail' => 'Set INSIGHTFACE_RENDER_URL in config/config.php.',
            'status' => 0,
        ];
    }

    $endpoint = rtrim($baseUrl, '/') . '/health';
    $headers = [
        'Accept: application/json',
    ];
    if (defined('INSIGHTFACE_RENDER_API_KEY') && INSIGHTFACE_RENDER_API_KEY !== '') {
        $headers[] = 'X-BEAS-API-Key: ' . INSIGHTFACE_RENDER_API_KEY;
    }

    $timeout = max(1, $timeoutSeconds);
    $connectTimeout = defined('INSIGHTFACE_HEALTH_CONNECT_TIMEOUT')
        ? (int) INSIGHTFACE_HEALTH_CONNECT_TIMEOUT
        : (defined('INSIGHTFACE_RENDER_CONNECT_TIMEOUT') ? (int) INSIGHTFACE_RENDER_CONNECT_TIMEOUT : 5);
    $connectTimeout = max(1, min($connectTimeout, $timeout));

    $response = renderInsightFaceGet($endpoint, $headers, $timeout, $connectTimeout, 1);
    if (!$response['ok']) {
        return $response + ['healthy' => false];
    }

    $decoded = json_decode($response['body'], true);
    if (is_array($decoded)) {
        $healthy = !array_key_exists('healthy', $decoded) || filter_var($decoded['healthy'], FILTER_VALIDATE_BOOL);
        if (array_key_exists('status', $decoded) && is_string($decoded['status'])) {
            $healthy = $healthy && !in_array(strtolower($decoded['status']), ['down', 'error', 'unhealthy'], true);
        }

        return [
            'ok' => true,
            'healthy' => $healthy,
            'status' => $response['status'],
            'detail' => $decoded['detail'] ?? $decoded['message'] ?? ($healthy ? 'healthy' : 'unhealthy'),
            'raw' => $decoded,
        ];
    }

    $body = trim((string) $response['body']);
    $healthy = $response['status'] >= 200 && $response['status'] < 300;
    if ($healthy && $body !== '') {
        $text = strtolower($body);
        if (str_contains($text, 'unhealthy') || str_contains($text, 'down') || str_contains($text, 'error')) {
            $healthy = false;
        }
    }

    return [
        'ok' => $healthy,
        'healthy' => $healthy,
        'status' => $response['status'],
        'detail' => $healthy ? ($body !== '' ? $body : 'healthy') : ($body !== '' ? $body : 'health probe failed'),
        'raw' => $body,
    ];
}

function insightfaceResponseLooksLikeTimeout(array $response): bool
{
    $text = strtolower((string) ($response['error'] ?? '') . ' ' . (string) ($response['detail'] ?? '') . ' ' . (string) ($response['raw'] ?? ''));
    return str_contains($text, 'timeout')
        || str_contains($text, 'timed out')
        || str_contains($text, 'resolve')
        || str_contains($text, 'connect')
        || str_contains($text, 'ssl')
        || str_contains($text, 'tls')
        || str_contains($text, 'network is unreachable')
        || str_contains($text, 'could not connect')
        || str_contains($text, 'connection refused');
}

function renderInsightFaceRaiseTimeLimit(int $timeoutSeconds, int $connectTimeoutSeconds): void
{
    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    if (function_exists('set_time_limit')) {
        $budget = max(120, $timeoutSeconds + $connectTimeoutSeconds + 30);
        @set_time_limit($budget);
    }
}

function renderInsightFaceRequest(string $url, string $payload, array $headers, int $timeoutSeconds, int $connectTimeoutSeconds): array
{
    renderInsightFaceRaiseTimeLimit($timeoutSeconds, $connectTimeoutSeconds);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $curlHeaders = array_merge($headers, [
            'Connection: keep-alive',
            'Expect:',
        ]);
        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_1_1') ? CURL_HTTP_VERSION_1_1 : 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '',
            CURLOPT_NOSIGNAL => true,
        ];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        curl_setopt_array($ch, $curlOptions);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno) {
            return [
                'ok' => false,
                'error' => 'render_request_failed',
                'detail' => $error ?: ('cURL error ' . $errno),
                'raw' => '',
            ];
        }

        if ($status < 200 || $status >= 300) {
            return [
                'ok' => false,
                'error' => 'render_http_' . $status,
                'detail' => $body,
                'raw' => $body,
            ];
        }

        return ['ok' => true, 'body' => $body, 'status' => $status];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", array_merge($headers, [
                'Connection: keep-alive',
                'Expect:',
            ])),
            'content' => $payload,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;
    $responseHeaders = function_exists('http_get_last_response_headers')
        ? (array) http_get_last_response_headers()
        : [];
    if (!empty($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match)) {
        $status = (int) $match[1];
    }

    if ($body === false) {
        return [
            'ok' => false,
            'error' => 'render_request_failed',
            'detail' => 'file_get_contents failed. Enable cURL or allow outbound HTTPS to Render.',
            'raw' => '',
        ];
    }

    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'error' => 'render_http_' . $status,
            'detail' => $body,
            'raw' => $body,
        ];
    }

    return ['ok' => true, 'body' => $body, 'status' => $status];
}

function renderInsightFaceGet(string $url, array $headers, int $timeoutSeconds, int $connectTimeoutSeconds, int $retries = 0): array
{
    $attempts = max(1, $retries + 1);
    $lastResponse = ['ok' => false, 'error' => 'render_request_failed', 'detail' => 'No request attempted'];

    renderInsightFaceRaiseTimeLimit($timeoutSeconds, $connectTimeoutSeconds);

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $curlHeaders = array_merge($headers, [
                'Connection: keep-alive',
                'Expect:',
            ]);
            $curlOptions = [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_1_1') ? CURL_HTTP_VERSION_1_1 : 1,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => '',
                CURLOPT_NOSIGNAL => true,
            ];
            if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
                $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
            }
            curl_setopt_array($ch, $curlOptions);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $errno) {
                $lastResponse = [
                    'ok' => false,
                    'error' => 'render_request_failed',
                    'detail' => $error ?: ('cURL error ' . $errno),
                    'raw' => '',
                    'status' => $status,
                ];
            } elseif ($status < 200 || $status >= 300) {
                $lastResponse = [
                    'ok' => false,
                    'error' => 'render_http_' . $status,
                    'detail' => $body,
                    'raw' => $body,
                    'status' => $status,
                ];
            } else {
                return ['ok' => true, 'body' => $body, 'status' => $status];
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", array_merge($headers, [
                        'Connection: keep-alive',
                        'Expect:',
                    ])),
                    'timeout' => $timeoutSeconds,
                    'ignore_errors' => true,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);
            $status = 0;
            $responseHeaders = function_exists('http_get_last_response_headers')
                ? (array) http_get_last_response_headers()
                : [];
            if (!empty($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match)) {
                $status = (int) $match[1];
            }

            if ($body === false) {
                $lastResponse = [
                    'ok' => false,
                    'error' => 'render_request_failed',
                    'detail' => 'file_get_contents failed. Enable cURL or allow outbound HTTPS to Railway.',
                    'raw' => '',
                    'status' => $status,
                ];
            } elseif ($status < 200 || $status >= 300) {
                $lastResponse = [
                    'ok' => false,
                    'error' => 'render_http_' . $status,
                    'detail' => $body,
                    'raw' => $body,
                    'status' => $status,
                ];
            } else {
                return ['ok' => true, 'body' => $body, 'status' => $status];
            }
        }

        if ($attempt < $attempts && insightfaceResponseLooksLikeTimeout($lastResponse)) {
            usleep(min(2000000, 350000 * $attempt));
        }
    }

    return $lastResponse;
}

function renderInsightFacePost(string $url, string $payload, array $headers, int $timeoutSeconds, int $connectTimeoutSeconds, int $retries = 0): array
{
    $attempts = max(1, $retries + 1);
    $lastResponse = ['ok' => false, 'error' => 'render_request_failed', 'detail' => 'No request attempted'];

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $lastResponse = renderInsightFaceRequest($url, $payload, $headers, $timeoutSeconds, $connectTimeoutSeconds);
        if ($lastResponse['ok']) {
            return $lastResponse;
        }

        if ($attempt < $attempts && insightfaceResponseLooksLikeTimeout($lastResponse)) {
            usleep(min(3000000, 500000 * $attempt));
        }
    }

    return $lastResponse;
}
