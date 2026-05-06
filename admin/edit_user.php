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

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
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
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name']  ?? '');
    $email = trim($_POST['email']       ?? '');
    if ($first === '' || $last === '' || $email === '') {
        flash_set('error', 'Please fill in all required fields.');
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

    $updateStmt = $pdo->prepare("UPDATE {$table} SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
    $updateStmt->execute([$first, $last, $email, $id]);
    
    flash_set('success', 'User profile updated successfully.');
    redirect('/admin/users.php');
}

require_once __DIR__ . '/../includes/header.php';
?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <div>
        <h1 style="margin-bottom:0;">Edit User</h1>
        <p class="muted" style="margin-top:4px;">Update details for <?= esc($targetUser['first_name'] . ' ' . $targetUser['last_name']) ?> (<?= esc($targetUser['institutional_id']) ?>)</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/admin/users.php" class="btn secondary">Back to List</a>
</div>

<form class="panel" method="post" style="max-width:600px;">
    <input type="hidden" name="edit_user" value="1">
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
    <div class="form-actions" style="margin-top:14px; display:flex; gap:10px;">
        <button class="btn" type="submit">Save Changes</button>
    </div>
</form>

<form method="post" class="panel" style="max-width:600px; margin-top:20px; border-color:var(--herald-red); background:rgba(230,57,70,0.05);">
    <h3 style="margin-top:0; color:var(--herald-red);">Danger Zone</h3>
    <p class="muted">Permanently removing this user will also delete all their assignments, materials, and attendance records. This cannot be undone.</p>
    <input type="hidden" name="delete_user" value="1">
    <button class="btn danger" type="submit" onclick="return confirm('Are you absolutely sure you want to permanently delete this user?');">Permanently Delete User</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
