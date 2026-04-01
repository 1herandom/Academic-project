<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');

/*
|--------------------------------------------------------------------------
| Feature 1 | Publish assignment details with brief file upload
|--------------------------------------------------------------------------
*/

$pdo = db();
$teacherId = current_user()['id'];

$courseStmt = $pdo->prepare("
    SELECT id, course_code, course_title 
    FROM courses 
    WHERE teacher_user_id = ? 
    ORDER BY course_code
");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {

    $courseId = (int)$_POST['course_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadlineAt = trim($_POST['deadline_at'] ?? '');
    $subjectLink = trim($_POST['subject_link'] ?? '');

    if ($courseId <= 0 || $title === '' || $description === '' || $deadlineAt === '') {
        flash_set('error', 'Please complete the assignment form.');
        redirect('/teacher/assignments.php');
    }

    $briefFilePath = null;

    if (!empty($_FILES['brief_file']['tmp_name'])) {

        $ext = strtolower(pathinfo($_FILES['brief_file']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pdf','docx'], true)) {
            flash_set('error', 'Brief file must be PDF or DOCX.');
            redirect('/teacher/assignments.php');
        }

        $name = safe_filename('brief_' . time() . '_' . $_FILES['brief_file']['name']);
        $briefFilePath = 'storage/uploads/briefs/' . $name;

        move_uploaded_file(
            $_FILES['brief_file']['tmp_name'],
            __DIR__ . '/../' . $briefFilePath
        );
    }

    $stmt = $pdo->prepare("
        INSERT INTO assignments 
        (course_id, title, description, deadline_at, subject_link, brief_file, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $courseId,
        $title,
        $description,
        $deadlineAt,
        $subjectLink ?: null,
        $briefFilePath,
        $teacherId
    ]);

    flash_set('success', 'Assignment published.');
    redirect('/teacher/assignments.php');
}

$assignments = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_title
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    WHERE a.created_by = ?
    ORDER BY a.deadline_at DESC
");

$assignments->execute([$teacherId]);
$assignments = $assignments->fetchAll();
?>

<h1>Publish Assignment</h1>

<div class="feature1-container">

<form class="feature1-panel" method="post" enctype="multipart/form-data">
    <input type="hidden" name="create_assignment" value="1">

    <div class="feature1-row">
        <label>Course
            <select name="course_id" required>
                <option value="">Choose course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>">
                        <?= esc($c['course_code'].' - '.$c['course_title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Deadline
            <input type="datetime-local" name="deadline_at" required>
        </label>
    </div>

    <label>Title
        <input type="text" name="title" required>
    </label>

    <label>Description
        <textarea name="description" required></textarea>
    </label>

    <label>Subject Link
        <input type="url" name="subject_link">
    </label>

    <label>Brief (PDF/DOCX)
        <input type="file" name="brief_file" accept=".pdf,.docx">
    </label>

    <button type="submit" class="feature1-btn">
        Publish Assignment
    </button>
</form>


<h3>Published Assignments</h3>

<table class="feature1-table">
    <thead>
        <tr>
            <th>Course</th>
            <th>Title</th>
            <th>Deadline</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($assignments as $a): ?>
        <tr>
            <td><?= esc($a['course_code']) ?></td>
            <td><?= esc($a['title']) ?></td>
            <td><?= esc($a['deadline_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>