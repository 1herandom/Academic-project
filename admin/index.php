<?php

require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

/*
|--------------------------------------------------------------------------
| Feature By | Bipin Guragain: Dynamic URL routing to the admin dashboard
| Feature By | Bipin Guragain: Admin user/course management entry point
| Feature By | Bipin Guragain: Pending password reset requests notification
|--------------------------------------------------------------------------
*/

$pdo          = db();
$adminCount   = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE status='active'")->fetchColumn();
$teacherCount = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='active'")->fetchColumn();
$studentCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$userCount    = $adminCount + $teacherCount + $studentCount;
$courseCount  = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// Pending password reset requests (new table)
$pendingPwdRequests = 0;
try {
    $pendingPwdRequests = (int)$pdo->query("SELECT COUNT(*) FROM password_requests WHERE status='pending'")->fetchColumn();
} catch (Exception $ignored) {}

// Total pending support requests (legacy)
$pendingSupportRequests = 0;
try {
    $pendingSupportRequests = (int)$pdo->query("SELECT COUNT(*) FROM support_requests WHERE status='pending'")->fetchColumn();
} catch (Exception $ignored) {}

$totalPending = $pendingPwdRequests + $pendingSupportRequests;
?>

<div class="page-hd">
    <h1>Academic Admin Dashboard</h1>
    <p>Manage users, courses, and batch enrollment securely across the Herald platform.</p>
</div>

<?php if ($totalPending > 0): ?>
<!-- Pending Requests Alert | Feature Maker: Bipin Guragain -->
<div class="panel mb-6" style="border-left: 4px solid var(--herald-gold); background: rgba(234,179,8,.07); display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
    <div style="font-size:2rem;">🔔</div>
    <div style="flex:1;">
        <strong style="font-size:1rem;">Pending Requests</strong>
        <p class="small muted mb-0 mt-1">
            <?php if ($pendingPwdRequests > 0): ?>
                <strong><?= $pendingPwdRequests ?></strong> password reset request<?= $pendingPwdRequests !== 1 ? 's' : '' ?> awaiting approval.
            <?php endif; ?>
            <?php if ($pendingSupportRequests > 0): ?>
                <?= $pendingPwdRequests > 0 ? ' &nbsp;|&nbsp; ' : '' ?>
                <strong><?= $pendingSupportRequests ?></strong> support request<?= $pendingSupportRequests !== 1 ? 's' : '' ?> pending.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= APP_BASE_URL ?>/admin/requests.php" class="btn amber" style="white-space:nowrap;">
        View All Requests
        <?php if ($totalPending > 0): ?>
            <span style="display:inline-flex;align-items:center;justify-content:center;background:rgba(0,0,0,.2);border-radius:999px;padding:0 7px;font-size:.7rem;margin-left:6px;font-weight:800;"><?= $totalPending ?></span>
        <?php endif; ?>
    </a>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="grid-4 mb-6">

    <div class="stat green">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </div>
        <span class="label">Total Users</span>
        <div class="value"><?= $userCount ?></div>
    </div>

    <div class="stat amber">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </div>
        <span class="label">Teachers</span>
        <div class="value"><?= $teacherCount ?></div>
    </div>

    <div class="stat green">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>
        <span class="label">Students</span>
        <div class="value"><?= $studentCount ?></div>
    </div>

    <div class="stat blue">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
        </div>
        <span class="label">Courses</span>
        <div class="value"><?= $courseCount ?></div>
    </div>

</div>

<!-- Quick action panels -->
<div class="grid-2">
    <div class="panel">
        <div class="d-flex flex-align-center gap-3 mb-14">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(132,204,22,0.12);
                        display:grid;place-items:center;color:var(--herald-green);flex-shrink:0;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <h3 style="margin:0;font-size:16px;font-weight:700;">User Management</h3>
        </div>
        <p class="small mb-4">Create, archive, and reset accounts while preserving all historical records for audit purposes.</p>
        <div class="form-actions">
            <a class="btn" href="<?= APP_BASE_URL ?>/admin/users.php">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                Manage Users
            </a>
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/passwords.php">Reset Passwords</a>
        </div>
    </div>

    <div class="panel">
        <div class="d-flex flex-align-center gap-3 mb-14">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(207,255,220,0.12);
                        display:grid;place-items:center;color:var(--herald-blue);flex-shrink:0;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <h3 style="margin:0;font-size:16px;font-weight:700;">Course Management</h3>
        </div>
        <p class="small mb-4">Add courses, assign teachers, and use CSV uploads to batch-enroll entire student cohorts instantly.</p>
        <div class="form-actions">
            <a class="btn amber" href="<?= APP_BASE_URL ?>/admin/courses.php">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                Manage Courses
            </a>
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/bulk_enroll.php">CSV Enrollment</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
