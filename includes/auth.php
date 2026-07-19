<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $isApiRequest = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
                        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
                        str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
        if ($isApiRequest) {
            jsonResponse(['error' => 'Unauthorized', 'code' => 'unauthorized'], 401);
        }
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'superadmin') {
        $isApiRequest = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
                        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
                        str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
        if ($isApiRequest) {
            jsonResponse(['error' => 'Forbidden', 'code' => 'forbidden'], 403);
        }
        header('Location: ' . APP_URL . '/dashboard.php?error=unauthorized');
        exit;
    }
}

function login(string $username, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND isActive=1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['login_time'] = time();
        return ['success' => true, 'user' => $user];
    }
    return ['success' => false, 'message' => 'Invalid credentials'];
}

function logout(): void {
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id']   ?? null,
        'username'  => $_SESSION['username']  ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
    ];
}

function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url, string $msg = '', string $type = 'success'): void {
    $sep = strpos($url, '?') !== false ? '&' : '?';
    if ($msg) $url .= $sep . $type . '=' . urlencode($msg);
    header('Location: ' . $url);
    exit;
}

function generateSessionId(): string {
    return 'SES-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
}

function timeAgo(string $datetime): string {
    $ts   = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    return date('d M Y', $ts);
}
?>
