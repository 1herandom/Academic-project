<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');


$pdo = db();
$teacherId = current_user()['id'];

$courseStmt = $pdo->prepare("SELECT c.id, c.course_code, c.course_title FROM courses c WHERE c.teacher_user_id = ? ORDER BY c.course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

$assignmentCount = (int)$pdo->query("SELECT COUNT(*) FROM assignments WHERE created_by = " . (int)$teacherId)->fetchColumn();
$materialCount = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE created_by = " . (int)$teacherId)->fetchColumn();
?>
<h1>Teacher Hub</h1>
<p class="muted">Manage attendance, assignments, and learning materials for your courses.</p>

<div class="grid-3">
    <div class="stat"><div class="label">My Courses</div><div class="value"><?= count($courses) ?></div></div>
    <div class="stat"><div class="label">Assignments</div><div class="value"><?= $assignmentCount ?></div></div>
    <div class="stat"><div class="label">Materials</div><div class="value"><?= $materialCount ?></div></div>
</div>

<div class="grid-2" style="margin-top:20px;">
    <div class="panel">
        <h3 style="margin-top:0;">Attendance</h3>
        <p class="small">Select L/T/W session type before taking attendance.</p>
        <a class="btn" href="<?= APP_BASE_URL ?>/teacher/attendance.php">Open Attendance</a>
    </div>
    <div class="panel">
        <h3 style="margin-top:0;">Assignments & Materials</h3>
        <p class="small">Publish tasks, upload briefs, and share lecture resources.</p>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/teacher/assignments.php">Open Assignments</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
