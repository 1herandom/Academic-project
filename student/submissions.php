<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Student');

/*
|--------------------------------------------------------------------------
| Feature 2 | Suprim: Assignment file upload engine with deadline enforcement
| Feature 3 | Suprim: Automated time-fencing with server-side deadline validation
|--------------------------------------------------------------------------
*/

$pdo = db();
$studentId = current_user()['id'];
$uploadDir = __DIR__ . '/../storage/uploads/assignments/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignmentId = (int)$_POST['assignment_id'];

    $stmt = $pdo->prepare("
        SELECT a.*, c.course_code, c.course_title
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        JOIN enrollments e ON e.course_id = c.id AND e.student_user_id = ?
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId, $assignmentId]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        flash_set('error', 'Assignment not found.');
        redirect('/student/submissions.php');
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $deadline = new DateTimeImmutable($assignment['deadline_at'], new DateTimeZone('UTC'));
    if ($now >= $deadline->modify('+1 minute')) {
        flash_set('error', 'Submission Closed.');
        redirect('/student/submissions.php');
    }

    if (empty($_FILES['submission_file']['tmp_name'])) {
        flash_set('error', 'Please upload a file.');
        redirect('/student/submissions.php?assignment_id=' . $assignmentId);
    }

    $size = (int)$_FILES['submission_file']['size'];
    if ($size > 20 * 1024 * 1024) {
        flash_set('error', 'Maximum file size is 20 MB.');
        redirect('/student/submissions.php?assignment_id=' . $assignmentId);
    }

    $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf','docx'], true)) {
        flash_set('error', 'Only PDF or DOCX files are accepted.');
        redirect('/student/submissions.php?assignment_id=' . $assignmentId);
    }

    $courseCode = $assignment['course_code'];
    $studentInfo = $pdo->prepare("SELECT institutional_id FROM users WHERE id = ?");
    $studentInfo->execute([$studentId]);
    $institutionalId = $studentInfo->fetchColumn();

    $timestamp = gmdate('Ymd_His');
    $storedName = safe_filename($courseCode . '_' . $institutionalId . '_' . $timestamp . '.' . $ext);
    $targetPath = $uploadDir . $storedName;
    if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $targetPath)) {
        flash_set('error', 'Upload failed.');
        redirect('/student/submissions.php?assignment_id=' . $assignmentId);
    }

    $existing = $pdo->prepare("SELECT id, stored_filename FROM submissions WHERE assignment_id = ? AND student_user_id = ?");
    $existing->execute([$assignmentId, $studentId]);
    $existingRow = $existing->fetch();

    if ($existingRow) {
        $oldPath = $uploadDir . $existingRow['stored_filename'];
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
        $upd = $pdo->prepare("UPDATE submissions SET original_filename = ?, stored_filename = ?, mime_type = ?, file_size = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
        $upd->execute([$_FILES['submission_file']['name'], $storedName, $_FILES['submission_file']['type'] ?: 'application/octet-stream', $size, $existingRow['id']]);
        flash_set('success', 'Submission replaced successfully.');
    } else {
        $ins = $pdo->prepare("INSERT INTO submissions (assignment_id, student_user_id, original_filename, stored_filename, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$assignmentId, $studentId, $_FILES['submission_file']['name'], $storedName, $_FILES['submission_file']['type'] ?: 'application/octet-stream', $size]);
        flash_set('success', 'Submission received.');
    }

    redirect('/student/submissions.php?course_id=' . (int)$assignment['course_id']);
}

$selectedCourseId = (int)($_GET['course_id'] ?? 0);

$coursesStmt = $pdo->prepare("SELECT c.id, c.course_code, c.course_title FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.student_user_id = ? ORDER BY c.course_code");
$coursesStmt->execute([$studentId]);
$courses = $coursesStmt->fetchAll();

$assignments = [];
if ($selectedCourseId) {
    $stmt = $pdo->prepare("
        SELECT a.*, c.course_code, c.course_title,
               s.id AS submission_id, s.stored_filename, s.original_filename, s.updated_at
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        JOIN enrollments e ON e.course_id = c.id AND e.student_user_id = ?
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_user_id = ?
        WHERE c.id = ?
        ORDER BY a.deadline_at ASC
    ");
    $stmt->execute([$studentId, $studentId, $selectedCourseId]);
    $assignments = $stmt->fetchAll();
}
?>
<h1>Assignments</h1>
<p class="muted">Upload only PDF or DOCX files up to 20 MB before the deadline. The newest submission replaces the old one.</p>

<form class="panel" method="get">
    <div class="form-row">
        <label><span class="small">Course</span>
            <select class="input" name="course_id" onchange="this.form.submit()">
                <option value="">Choose course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['course_code'] . ' - ' . $c['course_title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</form>

<?php foreach ($assignments as $a): 
    $deadline = new DateTimeImmutable($a['deadline_at'], new DateTimeZone('UTC'));
    $closed = (new DateTimeImmutable('now', new DateTimeZone('UTC'))) >= $deadline->modify('+1 minute');
?>
<div class="panel" style="margin-top:20px;">
    <h3 style="margin-top:0;"><?= esc($a['title']) ?></h3>
    <p class="small"><?= esc($a['course_code']) ?> · Deadline: <?= esc($a['deadline_at']) ?> UTC</p>
    <p><?= nl2br(esc($a['description'])) ?></p>
    <?php if ($a['subject_link']): ?>
        <p><a class="btn secondary" href="<?= esc($a['subject_link']) ?>" target="_blank" rel="noopener">Open Subject Link</a></p>
    <?php endif; ?>

    <?php if ($a['brief_file']): ?>
        <p><a class="btn secondary" href="<?= APP_BASE_URL . '/' . esc($a['brief_file']) ?>" target="_blank" rel="noopener">Download Brief</a></p>
    <?php endif; ?>

    <?php if ($a['submission_id']): ?>
        <div class="notice">Latest submission: <?= esc($a['original_filename']) ?> · Updated: <?= esc($a['updated_at']) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:14px;">
        <input type="hidden" name="submit_assignment" value="1">
        <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
        <input type="file" class="input" name="submission_file" accept=".pdf,.docx" <?= $closed ? 'disabled' : '' ?>>
        <div class="small" style="margin-top:8px;" data-deadline data-deadline="<?= esc($a['deadline_at']) ?> UTC">Deadline is server-validated.</div>
        <div class="form-actions" style="margin-top:10px;">
            <button class="btn" type="submit" data-submission-submit <?= $closed ? 'disabled' : '' ?>>
                <?= $closed ? 'Submission Closed' : 'Submit / Replace' ?>
            </button>
        </div>
    </form>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
