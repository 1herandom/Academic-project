<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
ensure_user_active();

$user = current_user();
$pdo  = db();

// ── Handle profile photo upload ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file     = $_FILES['profile_photo'];
    $maxBytes = 2 * 1024 * 1024; // 2 MB
    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $extMap   = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'Upload failed. Please try again.');
    } elseif ($file['size'] > $maxBytes) {
        flash_set('error', 'Photo must be under 2 MB.');
    } elseif (!in_array($file['type'], $allowed, true)) {
        flash_set('error', 'Only JPG, PNG, WEBP, or GIF photos are allowed.');
    } else {
        $ext      = $extMap[$file['type']] ?? 'jpg';
        $filename = (int)$user['id'] . '.' . $ext;
        $dir      = __DIR__ . '/storage/uploads/avatars/';

        // Remove old avatar files for this user (any extension)
        foreach (glob($dir . (int)$user['id'] . '.*') as $old) {
            @unlink($old);
        }

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            // Persist to DB and sync session
            $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
            $stmt->execute([$filename, $user['id']]);
            $_SESSION['user']['profile_photo'] = $filename;
            flash_set('success', 'Profile photo updated successfully.');
        } else {
            flash_set('error', 'Could not save photo. Check storage directory permissions.');
        }
    }
    redirect('/settings.php');
}

// Reload user from DB to get latest info
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $freshUser = $stmt->fetch() ?: $user;
} catch (\Exception $e) {
    $freshUser = $user;
}

$initials = user_initials($user);
$photoFilename = $_SESSION['user']['profile_photo'] ?? $freshUser['profile_photo'] ?? null;
$photoUrl = $photoFilename
    ? APP_BASE_URL . '/storage/uploads/avatars/' . esc($photoFilename)
    : null;

$memberSince = '';
if (!empty($freshUser['created_at'])) {
    $memberSince = date('F j, Y', strtotime($freshUser['created_at']));
}

// All POST handled — safe to output HTML
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hd">
    <h1>Account Settings</h1>
    <p>Manage your Herald profile, account info, and security preferences.</p>
</div>

<div class="settings-layout">

    <!-- ── Left: Profile Aside ──────────────────────────────── -->
    <aside class="settings-aside">
        <!-- Photo upload form (triggers hidden file input) -->
        <form method="post" enctype="multipart/form-data" id="photo-form">
            <div class="profile-photo-wrap" id="photo-preview">
                <?php if ($photoUrl): ?>
                    <img class="profile-photo"
                         src="<?= $photoUrl ?>?v=<?= time() ?>"
                         alt="<?= esc(user_display_name($user)) ?>">
                <?php else: ?>
                    <div class="profile-initials-lg"><?= esc($initials) ?></div>
                <?php endif; ?>

                <label class="photo-edit-btn" for="photo-file-input" title="Change photo">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </label>
            </div>

            <!-- Hidden file input — triggers on label click -->
            <input type="file"
                   id="photo-file-input"
                   name="profile_photo"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   style="display:none;">
        </form>

        <div class="settings-aside-name"><?= esc(user_display_name($user)) ?></div>
        <div class="settings-role-chip"><?= esc($user['role']) ?></div>

        <hr class="settings-aside-divider">

        <!-- Upload button (also triggers file input) -->
        <label for="photo-file-input" class="settings-aside-btn" style="cursor:pointer;">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Upload Photo
        </label>

        <p class="small mt-8" style="text-align:center;">JPG, PNG or WEBP · Max 2 MB</p>
    </aside>

    <!-- ── Right: Settings Sections ─────────────────────────── -->
    <div class="settings-main">

        <!-- Account Information -->
        <div class="settings-section">
            <div class="settings-section-hdr">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <h2>Account Information</h2>
            </div>
            <div class="settings-section-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?= esc(user_display_name($user)) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Institutional ID</span>
                    <span class="info-value" style="font-family:monospace;letter-spacing:0.05em;">
                        <?= esc($user['institutional_id']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email Address</span>
                    <span class="info-value"><?= esc($user['email']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value">
                        <span class="pill <?= $user['role'] === 'Academic Admin' ? 'gold' : ($user['role'] === 'Teacher' ? 'amber' : 'green') ?>">
                            <?= esc($user['role']) ?>
                        </span>
                    </span>
                </div>
                <?php if (!empty($user['teacher_code'])): ?>
                <div class="info-row">
                    <span class="info-label">Teacher Code</span>
                    <span class="info-value" style="font-family:monospace;"><?= esc($user['teacher_code']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($user['student_code'])): ?>
                <div class="info-row">
                    <span class="info-label">Student Code</span>
                    <span class="info-value" style="font-family:monospace;"><?= esc($user['student_code']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($memberSince): ?>
                <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?= esc($memberSince) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Account Status</span>
                    <span class="info-value">
                        <span class="pill green">Active</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Security -->
        <div class="settings-section">
            <div class="settings-section-hdr">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <h2>Security</h2>
            </div>
            <div class="settings-section-body">
                <p class="muted" style="font-size:13px;margin-top:10px;margin-bottom:14px;">
                    Keep your account secure by using a strong, unique password that you don't use anywhere else.
                </p>

                <a href="<?= APP_BASE_URL ?>/change_password.php" class="security-card" style="text-decoration:none;display:flex;">
                    <div class="security-card-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </div>
                    <div class="security-card-text">
                        <h3>Change Password</h3>
                        <p>Update your password to keep your account secure</p>
                    </div>
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                         style="width:16px;height:16px;color:var(--text-faint);align-self:center;flex-shrink:0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>

    </div><!-- /settings-main -->
</div><!-- /settings-layout -->

<!-- Auto-submit photo form when file is chosen -->
<script>
document.getElementById('photo-file-input')?.addEventListener('change', function() {
    if (this.files.length > 0) {
        document.getElementById('photo-form').submit();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
