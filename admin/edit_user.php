<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$table = $_GET['table'] ?? '';

if ($id <= 0 || !in_array($table, ['admins', 'teachers', 'students'], true)) {
    flash_set('error', 'Invalid user selected.');
    redirect('/admin/users.php');
}

$stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    flash_set('error', 'User not found.');
    redirect('/admin/users.php');
}

// AP-44 CSRF | Feature Maker: Bipin Guragain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect("/admin/edit_user.php?id={$id}&table={$table}");
    }
    if ($table === 'admins' && $id === current_user()['id']) {
        flash_set('error', 'You cannot delete your own account.');
        redirect("/admin/edit_user.php?id={$id}&table={$table}");
    }
    $delStmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    $delStmt->execute([$id]);
    flash_set('success', 'User permanently deleted.');
    redirect('/admin/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect("/admin/edit_user.php?id={$id}&table={$table}");
    }
    // AP-44: Sanitize name/email inputs
    $first        = htmlspecialchars(strip_tags(trim($_POST['first_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $last         = htmlspecialchars(strip_tags(trim($_POST['last_name']  ?? '')), ENT_QUOTES, 'UTF-8');
    $email        = trim($_POST['email']      ?? '');
    $personalEmail = trim($_POST['personal_email'] ?? '');
    if ($first === '' || $last === '' || $email === '') {
        flash_set('error', 'Please fill in all required fields.');
        redirect("/admin/edit_user.php?id={$id}&table={$table}");
    }
    if ($personalEmail !== '' && !filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Invalid personal email address.');
        redirect("/admin/edit_user.php?id={$id}&table={$table}");
    }

    // Check Duplicate Email
    $stmt = $pdo->prepare("
        SELECT SUM(c) FROM (
            SELECT COUNT(*) AS c FROM admins WHERE email=? AND NOT (id=? AND 'admins'=?)
            UNION ALL SELECT COUNT(*) FROM teachers WHERE email=? AND NOT (id=? AND 'teachers'=?)
            UNION ALL SELECT COUNT(*) FROM students WHERE email=? AND NOT (id=? AND 'students'=?)
        ) t");
    $stmt->execute([
        $email, $id, $table,
        $email, $id, $table,
        $email, $id, $table
    ]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash_set('error', 'Duplicate Email Address is not allowed.');
        redirect("/admin/edit_user.php?id={$id}&table={$table}");
    }

    // Check if personal_email column exists and update accordingly
    $hasPersonalEmail = column_exists($table, 'personal_email');
    if ($hasPersonalEmail) {
        $updateStmt = $pdo->prepare("UPDATE {$table} SET first_name = ?, last_name = ?, email = ?, personal_email = ? WHERE id = ?");
        $updateStmt->execute([$first, $last, $email, $personalEmail ?: null, $id]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE {$table} SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        $updateStmt->execute([$first, $last, $email, $id]);
    }
    
    flash_set('success', 'User profile updated successfully.');
    redirect('/admin/users.php');
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Edit User</h1>
        <p class="muted page-subtitle">Update details for <?= esc($targetUser['first_name'] . ' ' . $targetUser['last_name']) ?> (<?= esc($targetUser['institutional_id']) ?>)</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/admin/users.php" class="btn secondary">Back to List</a>
</div>

<form class="panel max-w-600" method="post">
    <input type="hidden" name="edit_user" value="1">
    <!-- AP-44 CSRF | Feature Maker: Bipin Guragain -->
    <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">
    <div class="form-row">
        <label><span class="small">First Name</span>
            <input class="input" type="text" name="first_name" value="<?= esc($targetUser['first_name']) ?>" required>
        </label>
        <label><span class="small">Last Name</span>
            <input class="input" type="text" name="last_name" value="<?= esc($targetUser['last_name']) ?>" required>
        </label>
    </div>
    <div class="form-row">
        <label><span class="small">Email Address</span>
            <input class="input" type="email" name="email" value="<?= esc($targetUser['email']) ?>" required>
        </label>
    </div>
    <div class="form-row">
        <label><span class="small">Personal Email</span>
            <input class="input" type="email" name="personal_email" value="<?= esc($targetUser['personal_email'] ?? '') ?>" placeholder="Optional personal email">
        </label>
    </div>
    <div class="form-actions mt-14 d-flex gap-10">
        <button class="btn" type="submit">Save Changes</button>
    </div>
</form>

<form method="post" class="panel max-w-600 mt-5 border-herald-red bg-danger-light">
    <h3 class="mt-0 color-herald-red">Delete Account</h3>
    <p class="muted">Permanently removing this user will also delete all their assignments, materials, and attendance records. This cannot be undone.</p>
    <input type="hidden" name="delete_user" value="1">
    <!-- AP-44 CSRF | Feature Maker: Bipin Guragain -->
    <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">
    <button class="btn danger" type="submit" onclick="return confirm('Are you absolutely sure you want to permanently delete this user?');">Permanently Delete User</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
