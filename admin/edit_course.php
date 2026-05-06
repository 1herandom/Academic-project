<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash_set('error', 'Invalid course selected.');
    redirect('/admin/courses.php');
}

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    flash_set('error', 'Course not found.');
    redirect('/admin/courses.php');
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    $courseCode = trim($_POST['course_code'] ?? '');
    $courseTitle = trim($_POST['course_title'] ?? '');
    $teacherId = (int)($_POST['teacher_user_id'] ?? 0);

    if ($courseCode === '' || $courseTitle === '') {
        flash_set('error', 'Course code and title are required.');
        redirect("/admin/edit_course.php?id={$id}");
    }

    $updateStmt = $pdo->prepare("UPDATE courses SET course_code = ?, course_title = ?, teacher_user_id = ? WHERE id = ?");
    $updateStmt->execute([$courseCode, $courseTitle, $teacherId ?: null, $id]);
    
    flash_set('success', 'Course updated successfully.');
    redirect('/admin/courses.php');
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $delStmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    $delStmt->execute([$id]);
    flash_set('success', 'Course deleted permanently.');
    redirect('/admin/courses.php');
}

$teachers = $pdo->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'active' ORDER BY first_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <div>
        <h1 style="margin-bottom:0;">Edit Course</h1>
        <p class="muted" style="margin-top:4px;">Update details for <?= esc($course['course_code']) ?></p>
    </div>
    <a href="<?= APP_BASE_URL ?>/admin/courses.php" class="btn secondary">Back to List</a>
</div>

<form class="panel" method="post" style="max-width:600px;">
    <input type="hidden" name="edit_course" value="1">
    <div class="form-row">
        <label><span class="small">Course Code</span>
            <input class="input" type="text" name="course_code" value="<?= esc($course['course_code']) ?>" required>
        </label>
        <label><span class="small">Course Title</span>
            <input class="input" type="text" name="course_title" value="<?= esc($course['course_title']) ?>" required>
        </label>
    </div>
    <div class="form-row one">
        <label><span class="small">Assigned Teacher</span>
            <select class="input" name="teacher_user_id">
                <option value="">Unassigned</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= $t['id'] === $course['teacher_user_id'] ? 'selected' : '' ?>>
                        <?= esc($t['first_name'] . ' ' . $t['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="form-actions" style="margin-top:14px;">
        <button class="btn" type="submit">Save Changes</button>
    </div>
</form>

<form method="post" class="panel" style="max-width:600px; margin-top:20px; border-color:var(--herald-red); background:rgba(230,57,70,0.05);">
    <h3 style="margin-top:0; color:var(--herald-red);">Danger Zone</h3>
    <p class="muted">Permanently removing this course will also delete all enrollments, attendance records, and materials associated with it. This cannot be undone.</p>
    <input type="hidden" name="delete_course" value="1">
    <button class="btn danger" type="submit" onclick="return confirm('Are you absolutely sure you want to permanently delete this course?');">Permanently Delete Course</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
