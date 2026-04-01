<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
ensure_user_active();
$user = current_user();
$flash = flash_get();
$activePath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
function nav_active(string $needle): string {
    global $activePath;
    return str_contains($activePath, $needle) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/style.css">
</head>
<body>
<div class="topbar">
    <div class="brand">
        <button class="hamburger" data-toggle-sidebar aria-label="Toggle sidebar">☰</button>
        <div class="brand-badge">S</div>
        <div>
            <div><?= esc(APP_NAME) ?></div>
            <div class="small">Role-based academic platform</div>
        </div>
    </div>
    <div class="small"><?= esc(user_display_name($user)) ?> · <?= esc($user['role']) ?></div>
</div>
<div class="sidebar-overlay" data-toggle-sidebar></div>
<div class="layout">
    <aside class="sidebar">
        <div class="nav-group">
            <div class="nav-title">Account</div>
            <a class="nav-link <?= nav_active('/dashboard.php') ?>" href="<?= APP_BASE_URL ?>/dashboard.php">Dashboard</a>
            <a class="nav-link <?= nav_active('/change_password.php') ?>" href="<?= APP_BASE_URL ?>/change_password.php">Change Password</a>
            <a class="nav-link" href="<?= APP_BASE_URL ?>/logout.php">Logout</a>
        </div>

        <?php if ($user['role'] === 'Academic Admin'): ?>
            <div class="nav-group">
                <div class="nav-title">Admin Tools</div>
                <a class="nav-link <?= nav_active('/admin/index.php') ?>" href="<?= APP_BASE_URL ?>/admin/index.php">Admin Overview</a>
                <a class="nav-link <?= nav_active('/admin/users.php') ?>" href="<?= APP_BASE_URL ?>/admin/users.php">User Management</a>
                <a class="nav-link <?= nav_active('/admin/courses.php') ?>" href="<?= APP_BASE_URL ?>/admin/courses.php">Course Management</a>
                <a class="nav-link <?= nav_active('/admin/bulk_enroll.php') ?>" href="<?= APP_BASE_URL ?>/admin/bulk_enroll.php">CSV Enrollment</a>
                <a class="nav-link <?= nav_active('/admin/passwords.php') ?>" href="<?= APP_BASE_URL ?>/admin/passwords.php">Password Reset</a>
            </div>
        <?php elseif ($user['role'] === 'Teacher'): ?>
            <div class="nav-group">
                <div class="nav-title">Teacher Hub</div>
                <a class="nav-link <?= nav_active('/teacher/index.php') ?>" href="<?= APP_BASE_URL ?>/teacher/index.php">Teacher Overview</a>
                <a class="nav-link <?= nav_active('/teacher/attendance.php') ?>" href="<?= APP_BASE_URL ?>/teacher/attendance.php">Attendance</a>
                <a class="nav-link <?= nav_active('/teacher/assignments.php') ?>" href="<?= APP_BASE_URL ?>/teacher/assignments.php">Assignments</a>
                <a class="nav-link <?= nav_active('/teacher/materials.php') ?>" href="<?= APP_BASE_URL ?>/teacher/materials.php">Materials</a>
            </div>
        <?php else: ?>
            <div class="nav-group">
                <div class="nav-title">Student Portal</div>
                <a class="nav-link <?= nav_active('/student/index.php') ?>" href="<?= APP_BASE_URL ?>/student/index.php">Student Overview</a>
                <a class="nav-link <?= nav_active('/student/attendance.php') ?>" href="<?= APP_BASE_URL ?>/student/attendance.php">Attendance Progress</a>
                <a class="nav-link <?= nav_active('/student/submissions.php') ?>" href="<?= APP_BASE_URL ?>/student/submissions.php">Assignments</a>
            </div>
        <?php endif; ?>
    </aside>
    <main class="content">
        <?php if ($flash): ?>
            <div class="flash <?= esc($flash['type']) ?>"><?= esc($flash['message']) ?></div>
        <?php endif; ?>
