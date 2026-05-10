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
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.add('light-mode');
        }
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herald</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/utilities.css">
    <?php if (basename($_SERVER['PHP_SELF']) === 'chatbot.php'): ?>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/chatbot.css">
    <?php elseif (strpos($_SERVER['REQUEST_URI'], '/admin/') === 0): ?>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/admin.css">
    <?php elseif (strpos($_SERVER['REQUEST_URI'], '/student/') === 0): ?>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/student.css">
    <?php elseif (strpos($_SERVER['REQUEST_URI'], '/teacher/') === 0): ?>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/teacher.css">
    <?php endif; ?>
</head>
<body>

<!-- ═══ TOPBAR ═══════════════════════════════════════════════ -->
<header class="topbar">
    <div class="brand">
        <button class="hamburger" data-toggle-sidebar aria-label="Toggle menu">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <a href="<?= APP_BASE_URL ?>/dashboard.php" style="display:flex; align-items:center; gap:1rem; text-decoration:none;">
            <div class="brand-badge">H</div>
            <div class="brand-text">
                <div class="brand-name">Herald</div>
                <div class="brand-tagline">Academic Platform</div>
            </div>
        </a>
    </div>

    <div class="topbar-right">
        <button id="theme-toggle" class="btn secondary sm theme-btn" aria-label="Toggle theme">
            <svg id="theme-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="theme-icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </button>
        <div class="topbar-user-info ml-2">
            <div class="topbar-user-name"><?= esc(user_display_name($user)) ?></div>
            <div class="topbar-user-role"><?= esc($user['role']) ?></div>
        </div>
        <a href="<?= APP_BASE_URL ?>/settings.php" style="display:flex; align-items:center; text-decoration:none;">
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
        </a>
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

        <a class="nav-link <?= nav_active('/chatbot.php') ?>"
           href="<?= APP_BASE_URL ?>/chatbot.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
            </svg>
            Herald AI
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

        <a class="nav-link <?= nav_active('/admin/notices.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/notices.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            Notice Board
        </a>

        <a class="nav-link <?= nav_active('/admin/passwords.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/passwords.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            Password Reset
        </a>

        <a class="nav-link <?= nav_active('/admin/requests.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/requests.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            Request Board
        </a>

        <a class="nav-link <?= nav_active('/admin/attendance.php') ?>"
           href="<?= APP_BASE_URL ?>/admin/attendance.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Attendance
        </a>
    </div>

    <?php elseif ($user['role'] === 'Teacher'): ?>
    <div class="nav-group">
        <span class="nav-title">Teacher Hub</span>

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

        <a class="nav-link <?= nav_active('/teacher/quizzes.php') ?>"
           href="<?= APP_BASE_URL ?>/teacher/quizzes.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
            Quizzes
        </a>

        <a class="nav-link <?= nav_active('/teacher/notices.php') ?>"
           href="<?= APP_BASE_URL ?>/teacher/notices.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            Notice Board
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
            Home
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

        <a class="nav-link <?= nav_active('/student/materials.php') ?>"
           href="<?= APP_BASE_URL ?>/student/materials.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            Materials
        </a>

        <a class="nav-link <?= nav_active('/student/quizzes.php') ?>"
           href="<?= APP_BASE_URL ?>/student/quizzes.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
            Quizzes
        </a>

        <a class="nav-link <?= nav_active('/student/notices.php') ?>"
           href="<?= APP_BASE_URL ?>/student/notices.php">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            Notice Board
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

<?php if (!empty($_SESSION['temp_password'])): ?>
    <div class="panel mb-4 border-success bg-success-light">
        <h3 class="mt-0 color-success flex-align-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Credential Generated
        </h3>
        <p>A new secure password has been generated. Please copy it now as it will not be shown again.</p>
        <div class="d-flex gap-10 flex-align-center mt-3">
            <input type="text" class="input font-mono font-bold max-w-240 bg-color color-herald-green text-16 border-herald-green" id="gen-password" value="<?= esc($_SESSION['temp_password']) ?>" readonly>
            <button class="btn green min-h-44" onclick="navigator.clipboard.writeText(document.getElementById('gen-password').value); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy to Clipboard', 2000);">Copy to Clipboard</button>
        </div>
    </div>
    <?php unset($_SESSION['temp_password']); ?>
<?php endif; ?>
