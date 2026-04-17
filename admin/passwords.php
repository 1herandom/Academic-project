<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

/*Feature 2: Rijan : Admin password reset and temporary password issuance*/

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId = (int)$_POST['user_id'];
    $newPassword = generate_temp_password();
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, temp_password = 1 WHERE id = ?");
    $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $userId]);
    // Use a special flash type so the JS knows to keep it visible longer
    flash_set('temp_password', 'Password reset. Temporary password: ' . $newPassword);
    redirect('/admin/passwords.php');
}

// All POST handled — safe to output HTML
require_once __DIR__ . '/../includes/header.php';

$users = $pdo->query("SELECT id, first_name, last_name, role, institutional_id, email FROM users WHERE status='active' ORDER BY role, first_name")->fetchAll();
?>
<h1>Password Reset</h1>
<p class="muted">Generate a temporary password for first login recovery.</p>

<div class="table-wrap">
    <table>
        <thead><tr><th>User</th><th>Role</th><th>Email</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= esc($u['first_name'] . ' ' . $u['last_name']) ?> (<?= esc($u['institutional_id']) ?>)</td>
                    <td><?= esc($u['role']) ?></td>
                    <td><?= esc($u['email']) ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="reset_password" value="1">
                            <button class="btn secondary" type="submit">Reset Password</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
