<?php
// Include configuration file (DB connection, constants)
require_once __DIR__ . '/config.php';

// Include authentication system
require_once __DIR__ . '/includes/auth.php';

// Ensure user is logged in and active
ensure_user_active();

// Get current logged-in user data
$user = current_user();

// Initialize database connection
$pdo  = db();

/*
|--------------------------------------------------------------------------
| PASSWORD CHANGE LOGIC
|--------------------------------------------------------------------------
*/

// Check if form is submitted using POST method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form inputs safely
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    // Fetch stored password hash from database
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $hash = (string)$stmt->fetchColumn();

    // Verify current password using secure hashing
    if (!password_verify($current, $hash)) {
        flash_set('error', 'Current password is incorrect.');
        redirect('/change_password.php');
    }

    // Validate new password (minimum 8 characters and match confirmation)
    if (strlen($new) < 8 || $new !== $confirm) {
        flash_set('error', 'New password must be at least 8 characters and match the confirmation.');
        redirect('/change_password.php');
    }

    // Update password using helper function (handles hashing internally)
    if (update_password((int)$user['id'], $new)) {
        flash_set('success', 'Password changed successfully. Keep it safe!');
        redirect('/settings.php');
    }

    // If update fails
    flash_set('error', 'Password update failed. Please try again.');
    redirect('/change_password.php');
}

// All POST processing complete — safe to render HTML
require_once __DIR__ . '/includes/header.php';
?>

<div class="focused-card page-animate">

    <!-- Back button to settings page -->
    <a href="<?= APP_BASE_URL ?>/settings.php"
       style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);margin-bottom:28px;transition:color var(--t-fast);"
       onmouseover="this.style.color='var(--herald-red)'"
       onmouseout="this.style.color='var(--text-muted)'">

        <!-- Back arrow icon -->
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>

        Back to Settings
    </a>

    <!-- Title and icon section -->
    <div style="text-align:center;margin-bottom:32px;">

        <!-- Lock icon -->
        <div class="focused-card-icon" style="margin:0 auto 18px;">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>

        <!-- Page heading -->
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-0.4px;margin:0 0 6px;">Change Password</h1>

        <!-- Security hint -->
        <p class="muted" style="font-size:14px;margin:0;">
            Use a long, random password to stay secure.
        </p>
    </div>

    <!-- Password change form -->
    <form method="post">

        <!-- Current password input -->
        <div class="form-group">
            <label for="pw-current">Current Password</label>
            <div class="input-wrap">
                <input class="input" id="pw-current" type="password"
                       name="current_password" placeholder="Your current password" required>

                <!-- Toggle visibility button -->
                <button type="button" class="pw-eye-btn" data-pw-toggle="pw-current" aria-label="Toggle">
                    <!-- Eye icon -->
                    <svg data-icon-eye fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- New password input -->
        <div class="form-group">
            <label for="pw-new">New Password</label>
            <div class="input-wrap">
                <input class="input" id="pw-new" type="password"
                       name="new_password" placeholder="Min 8 characters"
                       data-pw-strength required>
            </div>

            <!-- Password strength indicator -->
            <div class="pw-strength-wrap">
                <div class="pw-strength-bar">
                    <div class="pw-strength-fill"></div>
                </div>
                <div class="pw-strength-label"></div>
            </div>
        </div>

        <!-- Confirm password input -->
        <div class="form-group" style="margin-bottom:28px;">
            <label for="pw-confirm">Confirm New Password</label>
            <div class="input-wrap">
                <input class="input" id="pw-confirm" type="password"
                       name="confirm_password" placeholder="Re-enter new password" required>
            </div>
        </div>

        <!-- Submit button -->
        <button class="btn full lg" type="submit">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            Save New Password
        </button>
    </form>
</div>

<?php
// Include footer layout
require_once __DIR__ . '/includes/footer.php';
?>