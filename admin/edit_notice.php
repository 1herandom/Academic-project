<?php
/*
|--------------------------------------------------------------------------
| Feature By | Rijan Adhikari: Notice board features
  Feature By | Bipin Guragain: CSRF tokens and other security features
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

$user = current_user();
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

$stmt = db()->prepare("SELECT * FROM notices WHERE id = ? AND sender_role = 'Admin' AND sender_id = ?");
$stmt->execute([$id, $user['id']]);
$notice = $stmt->fetch();

if (!$notice) {
    flash_set('error', 'Notice not found or permission denied.');
    redirect('/admin/notices.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AP-44: CSRF validation
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect("/admin/edit_notice.php?id=$id");
    }
    // AP-44: Strip HTML/script tags from notice inputs (XSS prevention)
    $title       = htmlspecialchars(strip_tags(trim($_POST['title']   ?? '')), ENT_QUOTES, 'UTF-8');
    $content     = htmlspecialchars(strip_tags(trim($_POST['content'] ?? '')), ENT_QUOTES, 'UTF-8');
    $target_type = $_POST['target_type'] ?? '';
    
    $target_id = null;
    if ($target_type === 'course') {
        $target_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    } elseif ($target_type === 'teacher') {
        $target_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    } elseif ($target_type === 'student') {
        $target_id = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    }

    if ($title === '' || $content === '' || $target_type === '') {
        flash_set('error', 'Please fill in all required fields.');
        redirect("/admin/edit_notice.php?id=$id");
    }

    if (in_array($target_type, ['course', 'teacher', 'student']) && !$target_id) {
        flash_set('error', 'Please select a specific target.');
        redirect("/admin/edit_notice.php?id=$id");
    }

    $upd = db()->prepare("UPDATE notices SET title = ?, content = ?, target_type = ?, target_id = ? WHERE id = ? AND sender_role = 'Admin' AND sender_id = ?");
    $upd->execute([$title, $content, $target_type, $target_id, $id, $user['id']]);

    flash_set('success', 'Notice updated successfully.');
    redirect('/admin/notices.php');
}

// Fetch lists for dropdowns
$courses = db()->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code")->fetchAll();
$teachers = db()->query("SELECT id, first_name, last_name, institutional_id FROM teachers WHERE status='active' ORDER BY first_name")->fetchAll();
$students = db()->query("SELECT id, first_name, last_name, institutional_id FROM students WHERE status='active' ORDER BY first_name")->fetchAll();
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Edit Notice</h1>
        <p class="muted page-subtitle">Modify your announcement.</p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/admin/notices.php" class="btn secondary">Back to Notices</a>
    </div>
</div>

<div class="panel mx-auto" style="max-width: 600px;">
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
            <label for="title">Notice Title</label>
            <input class="input" type="text" id="title" name="title" value="<?= esc($notice['title']) ?>" required>
        </div>

        <div class="form-group">
            <label for="target_type">Target Audience</label>
            <select class="input" id="target_type" name="target_type" required onchange="toggleTargetDropdowns()">
                <option value="">-- Select Target --</option>
                <option value="all" <?= $notice['target_type'] === 'all' ? 'selected' : '' ?>>Everyone</option>
                <option value="all_teachers" <?= $notice['target_type'] === 'all_teachers' ? 'selected' : '' ?>>All Teachers</option>
                <option value="all_students" <?= $notice['target_type'] === 'all_students' ? 'selected' : '' ?>>All Students</option>
                <option value="course" <?= $notice['target_type'] === 'course' ? 'selected' : '' ?>>Specific Course</option>
                <option value="teacher" <?= $notice['target_type'] === 'teacher' ? 'selected' : '' ?>>Specific Teacher</option>
                <option value="student" <?= $notice['target_type'] === 'student' ? 'selected' : '' ?>>Specific Student</option>
            </select>
        </div>

        <div class="form-group <?= $notice['target_type'] === 'course' ? '' : 'd-none' ?>" id="course_select">
            <label for="course_id">Select Course</label>
            <select class="input" id="course_id" name="course_id">
                <option value="">-- Select Course --</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $notice['target_type'] === 'course' && $notice['target_id'] == $c['id'] ? 'selected' : '' ?>><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group <?= $notice['target_type'] === 'teacher' ? '' : 'd-none' ?>" id="teacher_select">
            <label for="teacher_id">Select Teacher</label>
            <select class="input" id="teacher_id" name="teacher_id">
                <option value="">-- Select Teacher --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $notice['target_type'] === 'teacher' && $notice['target_id'] == $t['id'] ? 'selected' : '' ?>><?= esc($t['first_name'] . ' ' . $t['last_name'] . ' (' . $t['institutional_id'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group <?= $notice['target_type'] === 'student' ? '' : 'd-none' ?>" id="student_select">
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
    document.getElementById('course_select').classList.toggle('d-none', type !== 'course');
    document.getElementById('teacher_select').classList.toggle('d-none', type !== 'teacher');
    document.getElementById('student_select').classList.toggle('d-none', type !== 'student');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
