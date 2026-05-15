<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user      = current_user();
$pdo       = db();
$teacherId = $user['id'];

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

// Fetch the assignment — ownership check baked in
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND created_by = ?");
$stmt->execute([$id, $teacherId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    flash_set('error', 'Assignment not found or permission denied.');
    redirect('/teacher/assignments.php');
}

// Courses taught by this teacher
$courseStmt = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId    = (int)($_POST['course_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadlineAt  = trim($_POST['deadline_at'] ?? '');
    $subjectLink = trim($_POST['subject_link'] ?? '');

    if ($courseId <= 0 || $title === '' || $description === '' || $deadlineAt === '') {
        flash_set('error', 'Please complete all required fields.');
        redirect("/teacher/edit_assignment.php?id=$id");
    }

    $newBriefFile = $assignment['brief_file']; // keep existing by default

    if (!empty($_FILES['brief_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['brief_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'docx'], true)) {
            flash_set('error', 'Brief file must be PDF or DOCX.');
            redirect("/teacher/edit_assignment.php?id=$id");
        }

        $name        = safe_filename('brief_' . time() . '_' . $_FILES['brief_file']['name']);
        $briefDir    = 'storage/uploads/briefs/';
        $absBriefDir = __DIR__ . '/../' . $briefDir;
        if (!is_dir($absBriefDir)) {
            mkdir($absBriefDir, 0755, true);
        }

        if (!move_uploaded_file($_FILES['brief_file']['tmp_name'], $absBriefDir . $name)) {
            flash_set('error', 'Failed to upload the new brief file.');
            redirect("/teacher/edit_assignment.php?id=$id");
        }

        // Remove old brief file from disk
        if (!empty($assignment['brief_file'])) {
            $oldAbs = __DIR__ . '/../' . $assignment['brief_file'];
            if (file_exists($oldAbs)) {
                @unlink($oldAbs);
            }
        }

        $newBriefFile = $briefDir . $name;
    }

    $upd = $pdo->prepare("UPDATE assignments SET course_id=?, title=?, description=?, deadline_at=?, subject_link=?, brief_file=? WHERE id=? AND created_by=?");
    $upd->execute([$courseId, $title, $description, $deadlineAt, $subjectLink ?: null, $newBriefFile, $id, $teacherId]);

    flash_set('success', 'Assignment updated successfully.');
    redirect('/teacher/assignments.php');
}

// Format deadline for datetime-local input (needs Y-m-d\TH:i)
$deadlineForInput = date('Y-m-d\TH:i', strtotime($assignment['deadline_at']));

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Edit Assignment</h1>
        <p class="muted page-subtitle">Update this assessment's details.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/teacher/assignments.php" class="btn secondary">Back to List</a>
</div>

<form class="panel" method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="form-row">
        <label><span class="small">Course</span>
            <select class="input" name="course_id" required>
                <option value="">Choose course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $assignment['course_id'] == $c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['course_code'] . ' - ' . $c['course_title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span class="small">Deadline</span>
            <input class="input" type="datetime-local" name="deadline_at" value="<?= esc($deadlineForInput) ?>" required>
        </label>
    </div>

    <div class="form-row one">
        <label><span class="small">Title</span>
            <input class="input" type="text" name="title" value="<?= esc($assignment['title']) ?>" required>
        </label>
    </div>

    <div class="form-row one">
        <label><span class="small">Description</span>
            <textarea name="description" class="input" required><?= esc($assignment['description']) ?></textarea>
        </label>
    </div>

    <div class="form-row one">
        <label><span class="small">Subject Link</span>
            <input class="input" type="url" name="subject_link" value="<?= esc($assignment['subject_link'] ?? '') ?>" placeholder="https://...">
        </label>
    </div>

    <?php if (!empty($assignment['brief_file'])): ?>
    <div class="form-row one">
        <p class="small muted">Current brief: <strong><?= esc(basename($assignment['brief_file'])) ?></strong>
            — upload a new file below to replace it, or leave empty to keep it.</p>
    </div>
    <?php endif; ?>

    <div class="form-row one">
        <label><span class="small">Replace Brief File (optional)</span>
            <input class="input" type="file" name="brief_file" accept=".pdf,.docx">
        </label>
    </div>

    <button class="btn" type="submit">Save Changes</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
