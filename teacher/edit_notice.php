<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user = current_user();
$pdo = db();
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

$stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ? AND sender_role = 'Teacher' AND sender_id = ?");
$stmt->execute([$id, $user['id']]);
$notice = $stmt->fetch();

if (!$notice) {
    flash_set('error', 'Notice not found or permission denied.');
    redirect('/teacher/notices.php');
}

// Fetch courses taught by this teacher
$coursesStmt = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
$coursesStmt->execute([$user['id']]);
$courses = $coursesStmt->fetchAll();
$courseIds = array_column($courses, 'id');

// Fetch students enrolled in these courses
$students = [];
if (!empty($courseIds)) {
    $placeholders = str_repeat('?,', count($courseIds) - 1) . '?';
    $studentsStmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.first_name, s.last_name, s.institutional_id 
        FROM students s
        JOIN enrollments e ON s.id = e.student_user_id
        WHERE e.course_id IN ($placeholders) AND s.status = 'active'
        ORDER BY s.first_name
    ");
    $studentsStmt->execute($courseIds);
    $students = $studentsStmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target_type = $_POST['target_type'] ?? '';
    
    $target_id = null;
    if ($target_type === 'course') {
        $target_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
        if (!in_array($target_id, $courseIds)) $target_id = null; // Security check
    } elseif ($target_type === 'student') {
        $target_id = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
        $validStudentIds = array_column($students, 'id');
        if (!in_array($target_id, $validStudentIds)) $target_id = null; // Security check
    } else {
        $target_type = ''; // Invalid for teacher
    }

    if ($title === '' || $content === '' || $target_type === '' || !$target_id) {
        flash_set('error', 'Please fill in all fields and select a valid target.');
        redirect("/teacher/edit_notice.php?id=$id");
    }

    $upd = $pdo->prepare("UPDATE notices SET title = ?, content = ?, target_type = ?, target_id = ? WHERE id = ? AND sender_role = 'Teacher' AND sender_id = ?");
    $upd->execute([$title, $content, $target_type, $target_id, $id, $user['id']]);

    flash_set('success', 'Notice updated successfully.');
    redirect('/teacher/notices.php');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Edit Notice</h1>
        <p class="muted page-subtitle">Modify your announcement.</p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/teacher/notices.php" class="btn secondary">Back to Notices</a>
    </div>
</div>

<div class="panel mx-auto" style="max-width: 600px;">
    <form method="post" action="">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
            <label for="title">Notice Title</label>
            <input class="input" type="text" id="title" name="title" value="<?= esc($notice['title']) ?>" required>
        </div>

        <div class="form-group">
            <label for="target_type">Target Audience</label>
            <select class="input" id="target_type" name="target_type" required onchange="toggleTargetDropdowns()">
                <option value="">-- Select Target --</option>
                <option value="course" <?= $notice['target_type'] === 'course' ? 'selected' : '' ?>>My Course</option>
                <option value="student" <?= $notice['target_type'] === 'student' ? 'selected' : '' ?>>My Student</option>
            </select>
        </div>

        <div class="form-group" id="course_select" style="<?= $notice['target_type'] === 'course' ? 'display:block;' : 'display:none;' ?>">
            <label for="course_id">Select Course</label>
            <select class="input" id="course_id" name="course_id">
                <option value="">-- Select Course --</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $notice['target_type'] === 'course' && $notice['target_id'] == $c['id'] ? 'selected' : '' ?>><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="student_select" style="<?= $notice['target_type'] === 'student' ? 'display:block;' : 'display:none;' ?>">
            <label for="student_id">Select Student</label>
            <select class="input" id="student_id" name="student_id">
                <option value="">-- Select Student --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $notice['target_type'] === 'student' && $notice['target_id'] == $s['id'] ? 'selected' : '' ?>><?= esc($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['institutional_id'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="content">Notice Content</label>
            <textarea class="input" id="content" name="content" rows="6" required><?= esc($notice['content']) ?></textarea>
        </div>

        <div class="mt-6">
            <button type="submit" class="btn full">Update Notice</button>
        </div>
    </form>
</div>

<script>
function toggleTargetDropdowns() {
    const type = document.getElementById('target_type').value;
    document.getElementById('course_select').style.display = (type === 'course') ? 'block' : 'none';
    document.getElementById('student_select').style.display = (type === 'student') ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
