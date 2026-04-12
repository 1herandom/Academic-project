<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
ensure_user_active();
$user    = current_user();
$flash   = flash_get();
$initials = user_initials($user);

$activePath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
function nav_active(string $needle): string {
    global $activePath;
    return str_contains($activePath, $needle) ? 'active' : '';
}

// Profile photo URL
$photoUrl = null;
if (!empty($user['profile_photo'])) {
    $photoUrl = APP_BASE_URL . '/storage/uploads/avatars/' . esc($user['profile_photo']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herald</title>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/style.css">
</head>
<body>

<!-- ═══ TOPBAR ═══════════════════════════════════════════════ -->
<header class="topbar">
    <div class="brand">
        <button class="hamburger" data-toggle-sidebar aria-label="Toggle menu">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <div class="brand-badge">H</div>
        <div class="brand-text">
            <div class="brand-name">Herald</div>
            <div class="brand-tagline">Academic Platform</div>
        </div>
    </div>

    <div class="topbar-right">
        <div class="topbar-user-info">
            <div class="topbar-user-name"><?= esc(user_display_name($user)) ?></div>
            <div class="topbar-user-role"><?= esc($user['role']) ?></div>
        </div>
        <?php if ($photoUrl): ?>
            <img id="topbar-avatar-el"
                 class="topbar-avatar"
                 src="<?= $photoUrl ?>"
                 alt="<?= esc(user_display_name($user)) ?>">
        <?php else: ?>
            <div id="topbar-avatar-el"
                 class="topbar-avatar topbar-avatar--initials"
                 title="<?= esc(user_display_name($user)) ?>">
                <?= esc($initials) ?>
            </div>
        <?php endif; ?>
    </div>
</header>

<div class="sidebar-overlay" data-toggle-sidebar></div>

<!-- ═══ LAYOUT ═══════════════════════════════════════════════ -->
<div class="layout">
<aside class="sidebar">

    <!-- Account group: always shown -->
    <div class="nav-group">
        <span class="nav-title">Account</span>

        <a class="nav-link <?= nav_active('/dashboard.') ?>"
           href="<?= APP_BASE_URL ?>/dashboard.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <a class="nav-link nav-settings <?= nav_active('/settings.php') ?>"
           href="<?= APP_BASE_URL ?>/settings.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Settings
        </a>

        <a class="nav-link nav-logout" href="#" data-logout-trigger>
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Log Out
        </a>
    </div>

    <!-- Role-specific nav -->
    <?php if ($user['role'] === 'Academic Admin'): ?>
    <div class="nav-group">
        <span class="nav-title">Admin Tools</span>

        <a class="nav-link <?= nav_active('/admin/index.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/index.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Overview
        </a>

        <a class="nav-link <?= nav_active('/admin/users.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/users.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            User Management
        </a>

        <a class="nav-link <?= nav_active('/admin/courses.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/courses.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            Course Management
        </a>

        <a class="nav-link <?= nav_active('/admin/bulk_enroll.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/bulk_enroll.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            CSV Enrollment
        </a>

        <a class="nav-link <?= nav_active('/admin/passwords.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/passwords.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            Password Reset
        </a>
    </div>

    <?php elseif ($user['role'] === 'Teacher'): ?>
    <div class="nav-group">
        <span class="nav-title">Teacher Hub</span>

        <a class="nav-link <?= nav_active('/teacher/index.php') ?>"
           href="<?= APP_BASE_URL ?>/teacher/index.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Overview
        </a>

        <a class="nav-link <?= nav_active('/teacher/attendance.php') ?>"
           href="<?= APP_BASE_URL ?>/teacher/attendance.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Attendance
        </a>

        <a class="nav-link <?= nav_active('/teacher/assignments.php') ?>"
           href="<?= APP_BASE_URL ?>/teacher/assignments.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            Assignments
        </a>

        <a class="nav-link <?= nav_active('/teacher/materials.php') ?>"
           href="<?= APP_BASE_URL ?>/teacher/materials.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            Materials
        </a>
    </div>

    <?php else: ?>
    <div class="nav-group">
        <span class="nav-title">Student Portal</span>

        <a class="nav-link <?= nav_active('/student/index.php') ?>"
           href="<?= APP_BASE_URL ?>/student/index.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            My Dashboard
        </a>

        <a class="nav-link <?= nav_active('/student/attendance.php') ?>"
           href="<?= APP_BASE_URL ?>/student/attendance.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Attendance
        </a>

        <a class="nav-link <?= nav_active('/student/submissions.php') ?>"
           href="<?= APP_BASE_URL ?>/student/submissions.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            Assignments
        </a>
    </div>
    <?php endif; ?>

</aside>

<main class="content">
<?php if ($flash): ?>
<div class="flash <?= esc($flash['type']) ?>">
    <?php if ($flash['type'] === 'error'): ?>
        <svg class="flash-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    <?php elseif ($flash['type'] === 'success'): ?>
        <svg class="flash-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    <?php else: ?>
        <svg class="flash-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    <?php endif; ?>
    <?= esc($flash['message']) ?>
</div>
<?php endif; ?>
