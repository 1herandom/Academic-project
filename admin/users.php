<?php
/*
|------------------------------------------------------------------------------------
  Feature By | Rijan Adhikari: Admin user/course management entry point
  Feature By | Bipin Guragain: Password reset request management & Approve/Reset Workflow
|------------------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');
// CSRF token for state-changing forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = db();
$adminPersonalEmail   = column_exists('admins', 'personal_email')   ? "IFNULL(personal_email,'') AS personal_email" : "email AS personal_email";
$teacherPersonalEmail = column_exists('teachers', 'personal_email') ? "IFNULL(personal_email,'') AS personal_email" : "email AS personal_email";
$studentPersonalEmail = column_exists('students', 'personal_email') ? "IFNULL(personal_email,'') AS personal_email" : "email AS personal_email";

// ── Toggle Status ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect('/admin/users.php');
    }
    $id = (int)$_POST['user_id'];
    $table = $_POST['table'] ?? '';
    if (in_array($table, ['admins', 'teachers', 'students'], true)) {
        $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'archived';
        $pdo->prepare("UPDATE {$table} SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        flash_set('success', 'Account status updated.');
    }
    redirect('/admin/users.php');
}

// ── Reset Password ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    // CSRF check (AP-44 Security)
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect('/admin/users.php');
    }
    $userId = (int)$_POST['user_id'];
    $table  = $_POST['table'] ?? '';
    $sendEmail = !empty($_POST['send_email']);

    if (in_array($table, ['admins', 'teachers', 'students'], true)) {
        $newPassword = generate_temp_password();
        $pdo->prepare("UPDATE {$table} SET password_hash = ?, temp_password = 1 WHERE id = ?")
            ->execute([password_hash($newPassword, PASSWORD_BCRYPT), $userId]);

        // Fetch user details for email
        $emailColumns = column_exists($table, 'personal_email') ? 'first_name, last_name, email, personal_email' : 'first_name, last_name, email';
        $usr = $pdo->prepare("SELECT {$emailColumns} FROM {$table} WHERE id = ?");
        $usr->execute([$userId]);
        $targetUser = $usr->fetch();

        if (!isset($targetUser['personal_email'])) {
            $targetUser['personal_email'] = null;
        }

        $_SESSION['temp_password'] = $newPassword;
        $emailResult = null;

        if ($sendEmail && $targetUser) {
            $recipient = $targetUser['personal_email'] ?? '';
            if ($recipient === '' || $recipient === null || $recipient === $targetUser['email']) {
                $emailResult = 'No personal email on file.';
            } else {
                $fullName  = trim($targetUser['first_name'] . ' ' . $targetUser['last_name']);
                $html      = build_password_reset_email($fullName, $newPassword, $targetUser['email']);
                $emailResult = send_smtp_email($recipient, $fullName, 'Your Herald Password Has Been Reset', $html);
            }
        }

        if ($emailResult === true) {
            flash_set('success', '✅ Password reset and email sent successfully to the user\'s personal email.');
        } elseif ($emailResult !== null && $emailResult !== true) {
            flash_set('error', "❌ Password reset but email failed: {$emailResult}");
        } else {
            flash_set('success', 'Password reset. Temp password: ' . $newPassword);
        }
        // Store for "Send to Personal Email" button
        $_SESSION['last_reset_user_id']    = $userId;
        $_SESSION['last_reset_table']      = $table;
        $_SESSION['last_reset_password']   = $newPassword;
        $_SESSION['last_reset_email']      = $targetUser['personal_email'] ?? null;
        $_SESSION['last_reset_name']       = trim(($targetUser['first_name'] ?? '') . ' ' . ($targetUser['last_name'] ?? ''));
        $_SESSION['last_reset_login_email']= $targetUser['email'] ?? '';
    }
    redirect('/admin/users.php');
}

// ── Send to Personal Email (post-reset standalone action) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_personal_email'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect('/admin/users.php');
    }
    $recipient  = trim($_POST['recipient_email'] ?? '');
    $toName     = htmlspecialchars(strip_tags(trim($_POST['to_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $loginEmail = htmlspecialchars(strip_tags(trim($_POST['login_email'] ?? '')), ENT_QUOTES, 'UTF-8');
    $tempPwd    = $_POST['temp_pwd'] ?? '';

    if ($recipient === '' || $tempPwd === '') {
        flash_set('error', 'Missing email address or password data.');
        redirect('/admin/users.php');
    }

    $html   = build_password_reset_email($toName, $tempPwd, $loginEmail);
    $result = send_smtp_email($recipient, $toName, 'Your Herald Password Has Been Reset', $html);

    if ($result === true) {
        flash_set('success', "✅ Password reset details sent successfully to {$recipient}.");
        // Clear the banner once email is sent
        unset($_SESSION['last_reset_user_id'], $_SESSION['last_reset_table'],
              $_SESSION['last_reset_password'], $_SESSION['last_reset_email'],
              $_SESSION['last_reset_name'], $_SESSION['last_reset_login_email']);
    } else {
        flash_set('error', "❌ Failed to send email: {$result}");
    }
    redirect('/admin/users.php');
}

$searchQuery = trim($_GET['search'] ?? '');
$roleFilter  = $_GET['filter_role'] ?? '';

$sql = "
SELECT * FROM (
    SELECT id, institutional_id, first_name, last_name, 'Academic Admin' AS role, email, {$adminPersonalEmail}, status, NULL AS teacher_code, NULL AS student_code, created_at, 'admins' AS table_name FROM admins
    UNION ALL
    SELECT id, institutional_id, first_name, last_name, 'Teacher' AS role, email, {$teacherPersonalEmail}, status, teacher_code, NULL AS student_code, created_at, 'teachers' AS table_name FROM teachers
    UNION ALL
    SELECT id, institutional_id, first_name, last_name, 'Student' AS role, email, {$studentPersonalEmail}, status, NULL AS teacher_code, student_code, created_at, 'students' AS table_name FROM students
) all_users
WHERE 1=1
";
$params = [];
if ($roleFilter !== '') { $sql .= " AND role = ?"; $params[] = $roleFilter; }
if ($searchQuery !== '') {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR institutional_id LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Retrieve temp password flash if any
require_once __DIR__ . '/../includes/header.php';
?>
<!-- ── Send to Personal Email Banner (post-reset) ────────────────────── -->
<?php if (!empty($_SESSION['last_reset_password']) && !empty($_SESSION['last_reset_email'])): ?>
<div class="panel mb-4" style="border-color:var(--herald-green);background:rgba(104,186,127,.06);">
    <h3 style="margin:0 0 8px;color:var(--herald-green);font-size:1rem;">📧 Send Reset Details to Personal Email</h3>
    <p class="small mb-0">Password reset for <strong><?= esc($_SESSION['last_reset_name']) ?></strong>.
        Temporary password: <code style="background:rgba(0,0,0,.06);padding:2px 8px;border-radius:6px;letter-spacing:1px;"><?= esc($_SESSION['last_reset_password']) ?></code></p>
    <p class="small muted mt-1">Personal email on file: <strong><?= esc($_SESSION['last_reset_email']) ?></strong></p>
    <form method="post" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="send_personal_email"  value="1">
        <input type="hidden" name="csrf_token"           value="<?= esc($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="recipient_email"      value="<?= esc($_SESSION['last_reset_email']) ?>">
        <input type="hidden" name="to_name"              value="<?= esc($_SESSION['last_reset_name']) ?>">
        <input type="hidden" name="login_email"          value="<?= esc($_SESSION['last_reset_login_email']) ?>">
        <input type="hidden" name="temp_pwd"             value="<?= esc($_SESSION['last_reset_password']) ?>">
        <button type="submit" class="btn amber"
                onclick="return confirm('Send password reset email to <?= esc(addslashes($_SESSION['last_reset_email'])) ?>?')">
            📧 Send to Personal Email
        </button>
        <form method="post" style="margin:0;">
            <input type="hidden" name="dismiss_reset_banner" value="1">
            <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
        </form>
        <button type="button" class="btn secondary"
                onclick="this.closest('.panel').remove(); fetch('<?= APP_BASE_URL ?>/admin/users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'dismiss_reset_banner=1&csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>'})">
            Dismiss
        </button>
    </form>
</div>
<?php
    // Do NOT unset yet — wait until email is sent or dismissed
endif;
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">User Management</h1>
        <p class="muted page-subtitle">Manage staff and student accounts.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/admin/add_user.php" class="btn">Add User</a>
</div>

<form method="get" class="panel" style="margin-top:20px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
    <label style="flex:1; min-width:200px; margin-bottom:0;"><span class="small">Search Users</span>
        <input class="input" type="text" name="search" value="<?= esc($searchQuery) ?>" placeholder="Name, Email, ID...">
    </label>
    <label style="width:200px; margin-bottom:0;"><span class="small">Filter Role</span>
        <select class="input" name="filter_role">
            <option value="">All Roles</option>
            <option <?= $roleFilter === 'Academic Admin' ? 'selected' : '' ?>>Academic Admin</option>
            <option <?= $roleFilter === 'Teacher'        ? 'selected' : '' ?>>Teacher</option>
            <option <?= $roleFilter === 'Student'        ? 'selected' : '' ?>>Student</option>
        </select>
    </label>
    <button class="btn" type="submit" style="min-height:42px;">Filter</button>
    <?php if ($searchQuery !== '' || $roleFilter !== ''): ?>
        <a href="<?= APP_BASE_URL ?>/admin/users.php" class="btn secondary" style="min-height:42px;">Clear</a>
    <?php endif; ?>
</form>

<!-- Password Reset Modal -->
<div id="resetModal" style="display:none;position:fixed;inset:0;z-index:999;align-items:center;justify-content:center;background:transparent;">
    <div class="panel" style="width:420px;max-width:94vw;border-radius:20px;position:relative;">
        <button onclick="closeResetModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:1.4rem;">&times;</button>
        <h3 class="panel-title" style="margin-bottom:4px;">Reset Password</h3>
        <p class="muted small" id="resetModalDesc" style="margin-bottom:20px;"></p>
        <form method="post" id="resetForm">
            <input type="hidden" name="reset_password" value="1">
            <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="user_id" id="resetUserId">
            <input type="hidden" name="table"   id="resetTable">

            <label style="display:flex;align-items:center;gap:10px;margin-bottom:18px;cursor:pointer;">
                <input type="checkbox" name="send_email" id="sendEmailCheck" style="width:18px;height:18px;">
                <span>Auto-send new password to user's email</span>
            </label>
            <p id="emailTargetNote" class="small muted" style="margin:-10px 0 18px;display:none;"></p>

            <div style="display:flex;gap:10px;">
                <button class="btn danger" type="submit" style="flex:1;">Reset Password</button>
                <button type="button" onclick="closeResetModal()" class="btn secondary" style="flex:1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="table-wrap" style="margin-top:20px;">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Institutional ID</th>
                <th>Role</th>
                <th>Login Email</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= esc($u['first_name'] . ' ' . $u['last_name']) ?></td>
                <td><?= esc($u['institutional_id']) ?></td>
                <td><?= esc($u['role']) ?></td>
                <td><?= esc($u['email']) ?></td>
                <td><span class="pill <?= $u['status'] === 'active' ? 'green' : 'red' ?>"><?= esc($u['status']) ?></span></td>
                <td><?= esc(substr($u['created_at'], 0, 10)) ?></td>
                <td style="white-space:nowrap;">
                    <div style="position:relative; display:inline-block;" class="action-dropdown-container">
                        <button class="btn secondary" style="min-height:34px;padding:6px 14px;font-size:12px;" onclick="toggleActionMenu(this)">Actions ▾</button>
                        <div class="action-dropdown-menu" style="display:none; position:absolute; right:0; top:100%; min-width:130px; background:var(--bg-color, white); border:1px solid var(--border-color, #eee); border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,.1); z-index:10; flex-direction:column; padding:4px; margin-top:4px;">
                            <?php if ($u['role'] === 'Student'): ?>
                                <a href="<?= APP_BASE_URL ?>/admin/performance.php?search=<?= urlencode($u['institutional_id']) ?>"
                                   class="dropdown-item">Stats</a>
                            <?php endif; ?>

                            <a href="<?= APP_BASE_URL ?>/admin/edit_user.php?id=<?= (int)$u['id'] ?>&table=<?= esc($u['table_name']) ?>"
                               class="dropdown-item">Edit</a>

                            <!-- Start Chat -->
                            <?php if ($u['role'] !== 'Academic Admin'): ?>
                            <a href="<?= APP_BASE_URL ?>/chat_start.php?role=<?= urlencode($u['role']) ?>&id=<?= (int)$u['id'] ?>"
                               class="dropdown-item"
                               title="Open chat with <?= esc($u['first_name']) ?>">
                               Chat
                            </a>
                            <?php endif; ?>

                            <!-- Reset Password -->
                            <button class="dropdown-item"
                                onclick="openResetModal(
                                    <?= (int)$u['id'] ?>,
                                    '<?= esc($u['table_name']) ?>',
                                    '<?= esc(addslashes($u['first_name'] . ' ' . $u['last_name'])) ?>',
                                    '<?= esc(addslashes($u['personal_email'] ?? '')) ?>',
                                    '<?= esc(addslashes($u['email'] ?? '')) ?>'
                                )">Reset Pwd</button>

                            <!-- Archive/Restore -->
                            <form method="post" style="display:block; margin:0;">
                                <input type="hidden" name="csrf_token"   value="<?= esc($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="user_id"      value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="table"        value="<?= esc($u['table_name']) ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="new_status"   value="<?= $u['status'] === 'active' ? 'archived' : 'active' ?>">
                                <button class="dropdown-item" type="submit">
                                    <?= $u['status'] === 'active' ? 'Archive' : 'Restore' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="7" class="muted" style="text-align:center;padding:32px;">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.dropdown-item {
    display: block;
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    padding: 8px 12px;
    color: var(--text-main, #333);
    text-decoration: none;
    font-size: 12px;
    border-radius: 4px;
    cursor: pointer;
    font-family: inherit;
}
.dropdown-item:hover {
    background-color: rgba(0,0,0,0.04);
    text-decoration: none;
    color: var(--text-main, #333);
}
</style>

<script>
document.addEventListener('click', function(e) {
    if(!e.target.closest('.action-dropdown-container')) {
        document.querySelectorAll('.action-dropdown-menu').forEach(m => m.style.display = 'none');
    }
});

function toggleActionMenu(btn) {
    const menu = btn.nextElementSibling;
    const isVisible = menu.style.display === 'flex';
    document.querySelectorAll('.action-dropdown-menu').forEach(m => m.style.display = 'none');
    if(!isVisible) {
        menu.style.display = 'flex';
    }
}

function openResetModal(userId, table, name, personalEmail, institutionalEmail) {
    document.querySelectorAll('.action-dropdown-menu').forEach(m => m.style.display = 'none');
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetTable').value  = table;
    document.getElementById('resetModalDesc').textContent = 'Resetting password for: ' + name;

    const note = document.getElementById('emailTargetNote');
    const check = document.getElementById('sendEmailCheck');
    
    // Determine if personalEmail is a real personal email or the fallback institutional email
    const hasRealPersonalEmail = personalEmail && personalEmail !== institutionalEmail;
    
    if (hasRealPersonalEmail) {
        note.textContent = '📧 Personal email: ' + personalEmail;
        note.style.display = 'block';
        check.checked = true;
        check.disabled = false;
    } else {
        note.textContent = '⚠️ User has no personal email on file. Cannot auto-send.';
        note.style.display = 'block';
        check.checked = false;
        check.disabled = true;
    }

    const modal = document.getElementById('resetModal');
    modal.style.display = 'flex';
}
function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
}
document.getElementById('resetModal').addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
