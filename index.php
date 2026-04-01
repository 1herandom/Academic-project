<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $role = current_user()['role'];
    if ($role === 'Academic Admin') redirect('/admin/index.php');
    if ($role === 'Teacher') redirect('/teacher/index.php');
    redirect('/student/index.php');
}

handle_login();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc(APP_NAME) ?> Login</title>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/style.css">
</head>
<body class="auth-shell">
    <div class="centered grid-2" style="width:100%; align-items:stretch;">
        <section class="card">
            <div class="brand" style="margin-bottom:18px;">
                <div class="brand-badge">S</div>
                <div>
                    <h1 style="margin:0;">SMART EDU</h1>
                    <div class="small">Academic Admin · Teacher · Student</div>
                </div>
            </div>
            <h2 style="margin-top:0;">Sign in</h2>
            <p class="muted">Use your institutional ID and password to access the correct dashboard.</p>
            <?php if ($flash): ?><div class="flash <?= esc($flash['type']) ?>"><?= esc($flash['message']) ?></div><?php endif; ?>
            <form method="post" action="">
                <div class="form-row one">
                    <label><span class="small">Institutional ID</span><input class="input" type="text" name="institutional_id" placeholder="Enter Institutional ID" required></label>
                </div>
                <div class="form-row one">
                    <label><span class="small">Password</span><input class="input" type="password" name="password" placeholder="••••••••" required></label>
                </div>
                <div class="form-row one" style="display:flex;align-items:center;gap:10px;">
                    <label style="display:flex;align-items:center;gap:10px;"><input type="checkbox" name="remember_me" value="1"><span class="small">Remember me</span></label>
                </div>
                <div class="form-actions"><button class="btn" type="submit">Login</button></div>
            </form>
        </section>
        <section class="card">
            <h3 style="margin-top:0;">Platform notes</h3>
            <div class="notice">Role-specific routing is enforced after login, and every protected page validates access on the server.</div>
            <div style="margin-top:16px;">
                <div class="pill green">Academic Admin</div><p class="small">User management, course setup, and CSV enrollment.</p>
                <div class="pill amber">Teacher</div><p class="small">Attendance, assignments, and course materials.</p>
                <div class="pill red">Student</div><p class="small">Attendance progress and assignment submissions.</p>
            </div>
        </section>
    </div>
</body>
</html>
