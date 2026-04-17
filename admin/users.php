<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

/*
|--------------------------------------------------------------------------
| Feature 2 | Bipin Guragain: User Management CRUD
| - Create Academic Admin / Teacher / Student accounts
| - Generate teacher/student email format
| - Generate temporary password for first login
| - Prevent duplicate Institutional IDs
|--------------------------------------------------------------------------
*/

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $institutional_id = trim($_POST['institutional_id'] ?? '');
    $role = $_POST['role'] ?? '';
    if ($first === '' || $last === '' || $institutional_id === '' || !in_array($role, ['Academic Admin','Teacher','Student'], true)) {
        flash_set('error', 'Please fill in all required fields.');
        redirect('/admin/users.php');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE institutional_id = ?");
    $stmt->execute([$institutional_id]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash_set('error', 'Duplicate Institutional ID is not allowed.');
        redirect('/admin/users.php');
    }

    $teacherCode = null;
    $studentCode = null;
    if ($role === 'Teacher') {
        $teacherCode = unique_code(4, 'teacher_code');
    } elseif ($role === 'Student') {
        $studentCode = unique_code(8, 'student_code');
    }

    $email = build_email($first, $last, $role, $teacherCode ?? '', $studentCode ?? '');
    $tempPassword = generate_temp_password();
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (institutional_id, first_name, last_name, role, email, teacher_code, student_code, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active')");
    $stmt->execute([$institutional_id, $first, $last, $role, $email, $teacherCode, $studentCode, $hash]);

    flash_set('temp_password', "User created. Temporary password: {$tempPassword} | Email: {$email}");
    redirect('/admin/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = (int)$_POST['user_id'];
    $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'archived';
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    flash_set('success', 'Account status updated.');
    redirect('/admin/users.php');
}

// All POST handled above — now safe to output HTML
require_once __DIR__ . '/../includes/header.php';

$users = $pdo->query("SELECT id, institutional_id, first_name, last_name, role, email, status, teacher_code, student_code, created_at FROM users ORDER BY id DESC")->fetchAll();
?>
<h1>User Management</h1>
<p class="muted">Create staff and student accounts with role-specific institutional email formats.</p>

<div class="grid-2">
    <form class="panel" method="post" autocomplete="off">
        <h3 style="margin-top:0;">Create Account</h3>
        <input type="hidden" name="create_user" value="1">
        <div class="form-row">
            <label><span class="small">First Name</span><input class="input" type="text" name="first_name" autocomplete="off" required></label>
            <label><span class="small">Last Name</span><input class="input" type="text" name="last_name" autocomplete="off" required></label>
        </div>
        <div class="form-row">
            <label><span class="small">Unique Institutional ID</span><input class="input" type="text" name="institutional_id" autocomplete="off" required></label>
            <label><span class="small">Role</span>
                <select name="role" class="input" required>
                    <option value="">Select Role</option>
                    <option>Academic Admin</option>
                    <option>Teacher</option>
                    <option>Student</option>
                </select>
            </label>
        </div>
        <div class="notice">
            Teacher email format: <strong>full.name + teacher id + @smart.edu.np</strong><br>
            Student email format: <strong>NP + student id + @smart.edu.np</strong>
        </div>
        <div class="form-actions" style="margin-top:14px;">
            <button class="btn" type="submit">Create User</button>
        </div>
    </form>

    <div class="panel">
        <h3 style="margin-top:0;">Account Rules</h3>
        <p class="small">Institutional IDs are unique across the entire database.</p>
        <p class="small">Teacher IDs are generated as 4-digit codes. Student IDs are generated as 8-digit codes.</p>
        <p class="small">Temporary passwords are issued on first login and can be changed later.</p>
    </div>
</div>

<div class="table-wrap" style="margin-top:20px;">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Institutional ID</th>
                <th>Role</th>
                <th>Email</th>
                <th>Code</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= esc($u['first_name'] . ' ' . $u['last_name']) ?></td>
                <td><?= esc($u['institutional_id']) ?></td>
                <td><?= esc($u['role']) ?></td>
                <td><?= esc($u['email']) ?></td>
                <td><?= esc($u['teacher_code'] ?: $u['student_code'] ?: '-') ?></td>
                <td><span class="pill <?= $u['status'] === 'active' ? 'green' : 'red' ?>"><?= esc($u['status']) ?></span></td>
                <td><?= esc($u['created_at']) ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="toggle_status" value="1">
                        <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'archived' : 'active' ?>">
                        <button class="btn secondary" type="submit"><?= $u['status'] === 'active' ? 'Archive' : 'Restore' ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
