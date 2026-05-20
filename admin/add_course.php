<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $courseCode = trim($_POST['course_code'] ?? '');
    $courseTitle = trim($_POST['course_title'] ?? '');
    $teacherId = (int)($_POST['teacher_user_id'] ?? 0);

    if ($courseCode === '' || $courseTitle === '') {
        flash_set('error', 'Course code and title are required.');
        redirect('/admin/add_course.php');
    }

    // Check for duplicate course code
    $dupCheck = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
    $dupCheck->execute([$courseCode]);
    if ($dupCheck->fetch()) {
        flash_set('error', 'A course with code "' . esc($courseCode) . '" already exists. Please use a different course code.');
        redirect('/admin/add_course.php');
    }

    $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_title, teacher_user_id, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$courseCode, $courseTitle, $teacherId ?: null, current_user()['id']]);
    flash_set('success', 'Course created successfully.');
    redirect('/admin/courses.php');
}

$teachers = $pdo->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'active' ORDER BY first_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Add Course</h1>
        <p class="muted page-subtitle">Define a new course for your teachers and students.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/admin/courses.php" class="btn secondary">Back to List</a>
</div>

<div class="grid-2">
    <form class="panel" method="post">
        <h3 class="panel-title">Create Course</h3>
        <input type="hidden" name="create_course" value="1">
        <div class="form-row">
            <label><span class="small">Course Code</span><input class="input" type="text" name="course_code" placeholder="CS101" required></label>
            <label><span class="small">Course Title</span><input class="input" type="text" name="course_title" placeholder="Computer Fundamentals" required></label>
        </div>
        <div class="form-row one">
            <label><span class="small">Assigned Teacher</span>
                <select class="input" name="teacher_user_id">
                    <option value="">None</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= esc($t['first_name'] . ' ' . $t['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-actions form-actions-margin">
            <button class="btn" type="submit">Create Course</button>
        </div>
    </form>

    <div class="panel">
        <h3 class="panel-title">Enrollment Readiness</h3>
        <p class="small">Courses are used by attendance, assignments, and CSV batch enrollment.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
