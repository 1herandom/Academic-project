<?php
// Include configuration (database, constants)
require_once __DIR__ . '/../config.php';

// Include authentication system
require_once __DIR__ . '/auth.php';

// Ensure user is logged in and active
ensure_user_active();

// Get current logged-in user
$user = current_user();

// Get flash message (success/error/info)
$flash = flash_get();

// Generate user initials (used if no profile image)
$initials = user_initials($user);

// Get current URL path for active navigation highlighting
$activePath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

// Helper function to mark navigation links as active
function nav_active(string $needle): string {
    global $activePath;
    return str_contains($activePath, $needle) ? 'active' : '';
}

// Initialize profile photo URL
$photoUrl = null;

// Check if user has uploaded a profile photo
if (!empty($user['profile_photo'])) {

    // Generate full URL for profile image
    $photoUrl = APP_BASE_URL . '/storage/uploads/avatars/' . esc($user['profile_photo']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <!-- Page metadata -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title -->
    <title>Herald</title>

    <!-- Main stylesheet -->
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/style.css">
</head>
<body>

<!-- ═══ TOP NAVIGATION BAR ═══════════════════════════════════ -->
<header class="topbar">

    <!-- Branding section -->
    <div class="brand">

        <!-- Sidebar toggle button (hamburger menu) -->
        <button class="hamburger" data-toggle-sidebar aria-label="Toggle menu">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <!-- Logo badge -->
        <div class="brand-badge">H</div>

        <!-- Platform name -->
        <div class="brand-text">
            <div class="brand-name">Herald</div>
            <div class="brand-tagline">Academic Platform</div>
        </div>
    </div>

    <!-- Right side user info -->
    <div class="topbar-right">

        <!-- User name and role -->
        <div class="topbar-user-info">
            <div class="topbar-user-name"><?= esc(user_display_name($user)) ?></div>
            <div class="topbar-user-role"><?= esc($user['role']) ?></div>
        </div>

        <!-- Display profile image if available -->
        <?php if ($photoUrl): ?>
            <img id="topbar-avatar-el"
                 class="topbar-avatar"
                 src="<?= $photoUrl ?>"
                 alt="<?= esc(user_display_name($user)) ?>">

        <!-- Otherwise show initials -->
        <?php else: ?>
            <div id="topbar-avatar-el"
                 class="topbar-avatar topbar-avatar--initials"
                 title="<?= esc(user_display_name($user)) ?>">
                <?= esc($initials) ?>
            </div>
        <?php endif; ?>
    </div>
</header>

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" data-toggle-sidebar></div>

<!-- ═══ MAIN LAYOUT ══════════════════════════════════════════ -->
<div class="layout">

<aside class="sidebar">

    <!-- ACCOUNT SECTION (always visible) -->
    <div class="nav-group">
        <span class="nav-title">Account</span>

        <!-- Dashboard link -->
        <a class="nav-link <?= nav_active('/dashboard.') ?>"
           href="<?= APP_BASE_URL ?>/dashboard.php">
            Dashboard
        </a>

        <!-- Settings link -->
        <a class="nav-link nav-settings <?= nav_active('/settings.php') ?>"
           href="<?= APP_BASE_URL ?>/settings.php">
            Settings
        </a>

        <!-- Logout trigger -->
        <a class="nav-link nav-logout" href="#" data-logout-trigger>
            Log Out
        </a>
    </div>

    <!-- ROLE-BASED NAVIGATION -->
    <?php if ($user['role'] === 'Academic Admin'): ?>

        <!-- Admin menu -->
        <div class="nav-group">
            <span class="nav-title">Admin Tools</span>
            <!-- Links: Overview, User Management, Courses, CSV, Password Reset -->
        </div>

    <?php elseif ($user['role'] === 'Teacher'): ?>

        <!-- Teacher menu -->
        <div class="nav-group">
            <span class="nav-title">Teacher Hub</span>
            <!-- Links: Overview, Attendance, Assignments, Materials -->
        </div>

    <?php else: ?>

        <!-- Student menu -->
        <div class="nav-group">
            <span class="nav-title">Student Portal</span>
            <!-- Links: Dashboard, Attendance, Assignments -->
        </div>

    <?php endif; ?>

</aside>

<!-- MAIN CONTENT AREA -->
<main class="content">

<?php if ($flash): ?>

<?php
    // Check if flash message contains a temporary password
    $isTempPw = ($flash['type'] === 'temp_password');

    // Convert to success style if temp password
    $flashType = $isTempPw ? 'success' : $flash['type'];

    // Extract temporary password value
    $tempPwValue = '';

    if ($isTempPw) {
        // Extract password using regex
        if (preg_match('/Temporary password:\s*(\S+)/', $flash['message'], $m)) {
            $tempPwValue = $m[1];
        }
    }
?>

<!-- Flash message container -->
<div class="flash <?= esc($flashType) ?>"<?= $isTempPw ? ' data-temp-password' : '' ?>>

    <!-- If temp password exists → show copy button -->
    <?php if ($isTempPw && $tempPwValue): ?>

        <span style="flex:1;">
            Password reset. Temporary password:

            <!-- Highlighted password -->
            <code id="temp-pw-code"><?= esc($tempPwValue) ?></code>

            <!-- Copy button -->
            <button type="button"
                onclick="copyTempPassword('<?= esc($tempPwValue) ?>')">
                Copy
            </button>
        </span>

    <?php else: ?>

        <!-- Normal flash message -->
        <?= esc($flash['message']) ?>

    <?php endif; ?>

</div>
<?php endif; ?>