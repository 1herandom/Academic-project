<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$pdo = db();
$teacherId = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $courseId = (int)$_POST['course_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadlineAt = trim($_POST['deadline_at'] ?? '');
    $subjectLink = trim($_POST['subject_link'] ?? '');

    if ($courseId <= 0 || $title === '' || $description === '' || $deadlineAt === '') {
        flash_set('error', 'Please complete the assignment form.');
        redirect('/teacher/add_assignment.php');
    }

    $briefFilePath = null;
    if (!empty($_FILES['brief_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['brief_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','docx'], true)) {
            flash_set('error', 'Brief file must be PDF or DOCX.');
            redirect('/teacher/add_assignment.php');
        }
        $name = safe_filename('brief_' . time() . '_' . $_FILES['brief_file']['name']);
        $briefDir = 'storage/uploads/briefs/';
        $absBriefDir = __DIR__ . '/../' . $briefDir;
        if (!is_dir($absBriefDir)) mkdir($absBriefDir, 0755, true);
        
        $briefFilePath = $briefDir . $name;
        if (!move_uploaded_file($_FILES['brief_file']['tmp_name'], $absBriefDir . $name)) {
            flash_set('error', 'Failed to upload brief file.');
            redirect('/teacher/add_assignment.php');
        }
    }

    $stmt = $pdo->prepare("INSERT INTO assignments (course_id, title, description, deadline_at, subject_link, brief_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$courseId, $title, $description, $deadlineAt, $subjectLink ?: null, $briefFilePath, $teacherId]);
    flash_set('success', 'Assignment published.');
    redirect('/teacher/add_assignment.php');
}

$courseStmt = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Add Assignment</h1>
        <p class="muted page-subtitle">Publish assessment details and upload a brief.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/teacher/assignments.php" class="btn secondary">Back to List</a>
</div>

<form class="panel" method="post" enctype="multipart/form-data">
    <input type="hidden" name="create_assignment" value="1">
    <div class="form-row">
        <label><span class="small">Course</span>
            <select class="input" name="course_id" required>
                <option value="">Choose course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span class="small">Deadline</span><input class="input" type="datetime-local" name="deadline_at" required></label>
    </div>
    <div class="form-row one">
        <label><span class="small">Title</span><input class="input" type="text" name="title" required></label>
    </div>
    <div class="form-row one">
        <label><span class="small">Description</span><textarea name="description" class="input" required></textarea></label>
    </div>
    <div class="form-row one">
        <label><span class="small">Subject Link</span><input class="input" type="url" name="subject_link" placeholder="https://..."></label>
    </div>
    <div class="form-row one">
        <label><span class="small">PDF/DOCX Brief</span><input class="input" type="file" name="brief_file" accept=".pdf,.docx"></label>
    </div>
    <button class="btn" type="submit">Publish Assignment</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
