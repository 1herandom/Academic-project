<?php
require_once __DIR__ . '/../config.php';

function handle_login(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $institutional_id = trim($_POST['institutional_id'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember_me']);

    if ($institutional_id === '' || $password === '') {
        flash_set('error', 'Please enter your Institutional ID and password.');
        redirect('/index.php');
    }

    $stmt = db()->prepare("SELECT * FROM users WHERE institutional_id = ? AND status='active' LIMIT 1");
    $stmt->execute([$institutional_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash_set('error', 'ID not recognized.');
        redirect('/index.php');
    }

    login_user($user, $remember);

    if ((int)$user['temp_password'] === 1) {
        flash_set('info', 'Temporary password detected. Please change your password.');
    }
}

function update_password(int $userId, string $newPassword): bool {
    if (strlen($newPassword) < 8) return false;

    $stmt = db()->prepare("UPDATE users SET password_hash = ?, temp_password = 0 WHERE id = ?");
    return $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $userId]);
}

function ensure_user_active(): void {
    require_login();
    $user = current_user();

    if (($user['status'] ?? 'active') !== 'active') {
        logout_user();
        flash_set('error', 'Your account is archived.');
        redirect('/index.php');
    }
}
?>