<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_FULL_NAME ?> | BEAS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>

<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark">
                <span class="brand-hex">⬡</span>
            </div>
            <div>
                <div class="brand-name">BEAS</div>
                <div class="brand-sub">Auth System</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-group">
                <span class="nav-label">OVERVIEW</span>
                <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </a>
            </div>

            <div class="nav-group">
                <span class="nav-label">MANAGEMENT</span>
                <a href="<?= APP_URL ?>/modules/students/index.php" class="nav-item <?= str_contains($currentPage,'student')?'active':'' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Students
                </a>
                <a href="<?= APP_URL ?>/modules/courses/index.php" class="nav-item <?= str_contains($currentPage,'course')?'active':'' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    Courses
                </a>
                <a href="<?= APP_URL ?>/modules/attendance/verify.php" class="nav-item <?= str_contains($currentPage,'verify')?'active':'' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12l2 2 4-4"/><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                    Verify / Sign-In
                </a>
                <a href="<?= APP_URL ?>/modules/attendance/index.php" class="nav-item <?= ($currentPage==='index'&&str_contains($_SERVER['PHP_SELF'],'attendance'))?'active':'' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Attendance Log
                </a>
            </div>

            <div class="nav-group">
                <span class="nav-label">REPORTS</span>
                <a href="<?= APP_URL ?>/modules/reports/index.php" class="nav-item <?= str_contains($currentPage,'report')?'active':'' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Analytics
                </a>
            </div>

            <div class="nav-group">
                <span class="nav-label">SYSTEM</span>
                <?php if ($user['role'] === 'superadmin'): ?>
                <a href="<?= APP_URL ?>/modules/admin/users.php" class="nav-item <?= str_contains($currentPage,'users')?'active':'' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Admin Users
                </a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/logout.php" class="nav-item nav-item--danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign Out
                </a>
            </div>
        </nav>

        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
                <div class="user-role"><?= ucfirst($user['role']) ?></div>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <div class="topbar">
            <button class="topbar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
            <div class="topbar-right">
                <span class="system-badge">
                    <span class="pulse-dot"></span>
                    System Active
                </span>
            </div>
        </div>

        <div class="page-body">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert--success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert--error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>
