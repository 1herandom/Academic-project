<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
ensure_user_active();

$user = current_user();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password']     ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $hash = (string)$stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        flash_set('error', 'Current password is incorrect.');
        redirect('/change_password.php');
    }

    if (strlen($new) < 8 || $new !== $confirm) {
        flash_set('error', 'New password must be at least 8 characters and match the confirmation.');
        redirect('/change_password.php');
    }

    if (update_password((int)$user['id'], $new)) {
        flash_set('success', 'Password changed successfully. Keep it safe!');
        redirect('/settings.php');
    }

    flash_set('error', 'Password update failed. Please try again.');
    redirect('/change_password.php');
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="focused-card page-animate">
    <!-- Back link -->
    <a href="<?= APP_BASE_URL ?>/settings.php"
       style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);margin-bottom:28px;transition:color var(--t-fast);"
       onmouseover="this.style.color='var(--herald-red)'"
       onmouseout="this.style.color='var(--text-muted)'">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Settings
    </a>

    <!-- Icon & title -->
    <div style="text-align:center;margin-bottom:32px;">
        <div class="focused-card-icon" style="margin:0 auto 18px;">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-0.4px;margin:0 0 6px;">Change Password</h1>
        <p class="muted" style="font-size:14px;margin:0;">Use a long, random password to stay secure.</p>
    </div>

    <form method="post">

        <div class="form-group">
            <label for="pw-current">Current Password</label>
            <div class="input-wrap">
                <input class="input" id="pw-current" type="password"
                       name="current_password" placeholder="Your current password" required>
                <button type="button" class="pw-eye-btn" data-pw-toggle="pw-current" aria-label="Toggle">
                    <svg data-icon-eye fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg data-icon-eye-off style="display:none;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label for="pw-new">New Password</label>
            <div class="input-wrap">
                <input class="input" id="pw-new" type="password"
                       name="new_password" placeholder="Min 8 characters"
                       data-pw-strength required>
                <button type="button" class="pw-eye-btn" data-pw-toggle="pw-new" aria-label="Toggle">
                    <svg data-icon-eye fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg data-icon-eye-off style="display:none;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
            <!-- Strength bar -->
            <div class="pw-strength-wrap">
                <div class="pw-strength-bar">
                    <div class="pw-strength-fill"></div>
                </div>
                <div class="pw-strength-label"></div>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:28px;">
            <label for="pw-confirm">Confirm New Password</label>
            <div class="input-wrap">
                <input class="input" id="pw-confirm" type="password"
                       name="confirm_password" placeholder="Re-enter new password" required>
                <button type="button" class="pw-eye-btn" data-pw-toggle="pw-confirm" aria-label="Toggle">
                    <svg data-icon-eye fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg data-icon-eye-off style="display:none;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
        </div>

        <button class="btn full lg" type="submit">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            Save New Password
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
