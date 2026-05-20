<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $duration = (int)$_POST['duration'];

    if ($course_id > 0 && $title !== '' && $duration > 0) {
        $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, teacher_user_id, title, duration_minutes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $user['id'], $title, $duration]);
        flash_set('success', 'Quiz created successfully.');
        redirect('/teacher/create_quiz.php');
    } else {
        flash_set('error', 'Please fill all required fields correctly.');
    }
}

$courses = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ?");
$courses->execute([$user['id']]);
$courseList = $courses->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Create Quiz</h1>
        <p class="muted page-subtitle">Set up a new quiz for your students.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/teacher/quizzes.php" class="btn secondary">Cancel</a>
</div>

<div class="card centered">
    <form method="post">
        <div class="form-group">
            <label>Course</label>
            <select name="course_id" class="input" required>
                <option value="">Select Course</option>
                <?php foreach ($courseList as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Quiz Title</label>
            <input type="text" name="title" class="input" required placeholder="e.g. Midterm Examination">
        </div>
        <div class="form-group">
            <label>Duration (minutes)</label>
            <input type="number" name="duration" class="input" required min="1" value="30">
        </div>
        <button type="submit" class="btn full">Create Quiz</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
