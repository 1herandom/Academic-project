<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

/*
|--------------------------------------------------------------------------
| Feature 2 | Bipin Guragain: Admin course management for role dashboards
|--------------------------------------------------------------------------
*/

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $courseCode = trim($_POST['course_code'] ?? '');
    $courseTitle = trim($_POST['course_title'] ?? '');
    $teacherId = (int)($_POST['teacher_user_id'] ?? 0);

    if ($courseCode === '' || $courseTitle === '') {
        flash_set('error', 'Course code and title are required.');
        redirect('/admin/courses.php');
    }

    $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_title, teacher_user_id, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$courseCode, $courseTitle, $teacherId ?: null, current_user()['id']]);
    flash_set('success', 'Course created successfully.');
    redirect('/admin/courses.php');
}

// All POST handled — safe to output HTML
require_once __DIR__ . '/../includes/header.php';

$courses = $pdo->query("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) AS teacher_name FROM courses c LEFT JOIN users u ON c.teacher_user_id = u.id ORDER BY c.id DESC")->fetchAll();
$teachers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'Teacher' AND status = 'active' ORDER BY first_name")->fetchAll();
?>
<h1>Course Management</h1>

<div class="grid-2">
    <form class="panel" method="post">
        <h3 style="margin-top:0;">Create Course</h3>
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
        <button class="btn" type="submit">Create Course</button>
    </form>

    <div class="panel">
        <h3 style="margin-top:0;">Enrollment Readiness</h3>
        <p class="small">Courses are used by attendance, assignments, and CSV batch enrollment.</p>
    </div>
</div>

<div class="table-wrap" style="margin-top:20px;">
    <table>
        <thead>
            <tr><th>Course Code</th><th>Title</th><th>Teacher</th><th>Created</th></tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><?= esc($c['course_code']) ?></td>
                    <td><?= esc($c['course_title']) ?></td>
                    <td><?= esc($c['teacher_name'] ?: '-') ?></td>
                    <td><?= esc($c['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
