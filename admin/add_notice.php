<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

$user = current_user();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
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
        redirect('/admin/add_notice.php');
    }

    if (in_array($target_type, ['course', 'teacher', 'student']) && !$target_id) {
        flash_set('error', 'Please select a specific target.');
        redirect('/admin/add_notice.php');
    }

    $stmt = db()->prepare("INSERT INTO notices (title, content, sender_role, sender_id, target_type, target_id) VALUES (?, ?, 'Admin', ?, ?, ?)");
    $stmt->execute([$title, $content, $user['id'], $target_type, $target_id]);

    flash_set('success', 'Notice sent successfully.');
    redirect('/admin/notices.php');
}

// Fetch lists for dropdowns
$courses = db()->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code")->fetchAll();
$teachers = db()->query("SELECT id, first_name, last_name, institutional_id FROM teachers WHERE status='active' ORDER BY first_name")->fetchAll();
$students = db()->query("SELECT id, first_name, last_name, institutional_id FROM students WHERE status='active' ORDER BY first_name")->fetchAll();
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Send New Notice</h1>
        <p class="muted page-subtitle">Draft and send announcements to target groups.</p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/admin/notices.php" class="btn secondary">Back to Notices</a>
    </div>
</div>

<div class="panel mx-auto" style="max-width: 600px;">
    <form method="post" action="">
        <div class="form-group">
            <label for="title">Notice Title</label>
            <input class="input" type="text" id="title" name="title" required>
        </div>

        <div class="form-group">
            <label for="target_type">Target Audience</label>
            <select class="input" id="target_type" name="target_type" required onchange="toggleTargetDropdowns()">
                <option value="">-- Select Target --</option>
                <option value="all">Everyone</option>
                <option value="all_teachers">All Teachers</option>
                <option value="all_students">All Students</option>
                <option value="course">Specific Course</option>
                <option value="teacher">Specific Teacher</option>
                <option value="student">Specific Student</option>
            </select>
        </div>

        <div class="form-group d-none" id="course_select">
            <label for="course_id">Select Course</label>
            <select class="input" id="course_id" name="course_id">
                <option value="">-- Select Course --</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group d-none" id="teacher_select">
            <label for="teacher_id">Select Teacher</label>
            <select class="input" id="teacher_id" name="teacher_id">
                <option value="">-- Select Teacher --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= esc($t['first_name'] . ' ' . $t['last_name'] . ' (' . $t['institutional_id'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group d-none" id="student_select">
            <label for="student_id">Select Student</label>
            <select class="input" id="student_id" name="student_id">
                <option value="">-- Select Student --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= esc($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['institutional_id'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="content">Notice Content</label>
            <textarea class="input" id="content" name="content" rows="6" required></textarea>
        </div>

        <div class="mt-6">
            <button type="submit" class="btn full">Send Notice</button>
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
