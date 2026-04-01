<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

$pdo = db();
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$studentCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='Student' AND status='active'")->fetchColumn();
$teacherCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='Teacher' AND status='active'")->fetchColumn();
$courseCount = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
?>
<h1>Academic Admin Dashboard</h1>
<p class="muted">Manage users, courses, and batch enrollment securely.</p>

<div class="grid-4">
    <div class="stat"><div class="label">Users</div><div class="value"><?= $userCount ?></div></div>
    <div class="stat"><div class="label">Teachers</div><div class="value"><?= $teacherCount ?></div></div>
    <div class="stat"><div class="label">Students</div><div class="value"><?= $studentCount ?></div></div>
    <div class="stat"><div class="label">Courses</div><div class="value"><?= $courseCount ?></div></div>
</div>

<div class="grid-2" style="margin-top:20px;">
    <div class="panel">
        <h3 style="margin-top:0;">User Management</h3>
        <p class="small">Create, archive, and reset accounts while preserving historical records.</p>
        <a class="btn" href="<?= APP_BASE_URL ?>/admin/users.php">Open User Management</a>
    </div>
    <div class="panel">
        <h3 style="margin-top:0;">CSV Enrollment</h3>
        <p class="small">Batch enroll students into courses using a validated ID list.</p>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/bulk_enroll.php">Open CSV Upload</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
