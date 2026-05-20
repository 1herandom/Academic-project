<?php
require_once __DIR__ . '/../config.php';

/*
|-------------------------------------------------------------------------------------
| Feature By | Bipin Guragain: Post-login redirect to role dashboards
| Feature By | Suprim Pant: Credential validation and secure session security
|-------------------------------------------------------------------------------------
*/

function handle_login(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember_me']);

    if ($email === '' || $password === '') {
        flash_set('error', 'Please enter your email and password.');
        redirect('/index.php');
    }

    $tables = ['admins' => 'Academic Admin', 'teachers' => 'Teacher', 'students' => 'Student'];
    $user = null;
    $role = null;
    $table = null;

    foreach ($tables as $t => $r) {
        $stmt = db()->prepare("SELECT * FROM {$t} WHERE email = ? AND status='active' LIMIT 1");
        $stmt->execute([$email]);
        if ($u = $stmt->fetch()) {
            $user = $u;
            $role = $r;
            $table = $t;
            break;
        }
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash_set('error', 'ID or password not recognized.');
        redirect('/index.php');
    }

    $user['role'] = $role;
    $user['table'] = $table;

    login_user($user, $remember);

    if ((int)$user['temp_password'] === 1) {
        flash_set('info', 'Temporary password detected. Please change your password.');
    }

    if ($user['role'] === 'Academic Admin') redirect('/admin/index.php');
    if ($user['role'] === 'Teacher') redirect('/teacher/index.php');
    redirect('/student/index.php');
}

function update_password(int $userId, string $newPassword): bool {
    if (strlen($newPassword) < 8) return false;
    $user = current_user();
    if (!$user || empty($user['table'])) return false;
    $stmt = db()->prepare("UPDATE {$user['table']} SET password_hash = ?, temp_password = 0 WHERE id = ?");
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
