<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $result   = login($username, $password);
    if ($result['success']) {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BEAS — Secure Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="login-logo-hex">⬡</div>
            <div class="login-title">BEAS</div>
            <div class="login-inst"><?= APP_INSTITUTION ?><br><?= APP_DEPARTMENT ?></div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label">Username or Email</label>
                <input type="text" name="username" class="form-control"
                       placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group" style="margin-bottom:24px">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn--primary" style="width:100%;justify-content:center;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Authenticate
            </button>
        </form>

        <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border);text-align:center;font-size:.72rem;color:var(--text-muted);">
            <?= APP_FULL_NAME ?> v<?= APP_VERSION ?><br>
            Secured with JWT + bcrypt
        </div>
    </div>
</div>
</body>
</html>
