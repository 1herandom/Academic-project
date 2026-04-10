<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');

/*
|--------------------------------------------------------------------------
| Feature 1 | Suprim: Publish assignment details with brief file upload
| Feature 4 | Suprim: Upload categorized lecture slides and lab sheets
|--------------------------------------------------------------------------
*/

$pdo = db();
$teacherId = current_user()['id'];
$uploadDir = __DIR__ . '/../storage/uploads/briefs/';

$courseStmt = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
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
        move_uploaded_file($_FILES['brief_file']['tmp_name'], __DIR__ . '/../' . $briefFilePath);
    }

    $stmt = $pdo->prepare("INSERT INTO assignments (course_id, title, description, deadline_at, subject_link, brief_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$courseId, $title, $description, $deadlineAt, $subjectLink ?: null, $briefFilePath, $teacherId]);
    flash_set('success', 'Assignment published.');
    redirect('/teacher/assignments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $courseId = (int)$_POST['course_id'];
    $title = trim($_POST['material_title'] ?? '');
    $category = $_POST['category'] ?? '';
    $fileType = $_POST['file_type'] ?? '';
    $videoLink = trim($_POST['video_link'] ?? '');

    if ($courseId <= 0 || $title === '' || !in_array($category, ['Lecture Notes','Lab Sheets','Reading Material'], true) || !in_array($fileType, ['PDF','PPTX','MP4'], true)) {
        flash_set('error', 'Please complete the material form.');
        redirect('/teacher/assignments.php');
    }

    $filePath = null;
    if (in_array($fileType, ['PDF','PPTX'], true)) {
        if (empty($_FILES['material_file']['tmp_name'])) {
            flash_set('error', 'Please upload a file for PDF/PPTX material.');
            redirect('/teacher/assignments.php');
        }
        $ext = strtolower(pathinfo($_FILES['material_file']['name'], PATHINFO_EXTENSION));
        if (($fileType === 'PDF' && $ext !== 'pdf') || ($fileType === 'PPTX' && $ext !== 'pptx')) {
            flash_set('error', 'File type does not match the selected material type.');
            redirect('/teacher/assignments.php');
        }
        $name = safe_filename('material_' . time() . '_' . $_FILES['material_file']['name']);
        $dir = $fileType === 'PDF' ? 'storage/uploads/materials/' : 'storage/uploads/materials/';
        $filePath = $dir . $name;
        move_uploaded_file($_FILES['material_file']['tmp_name'], __DIR__ . '/../' . $filePath);
    }

    if ($fileType === 'MP4' && $videoLink === '') {
        flash_set('error', 'MP4 materials must include a link.');
        redirect('/teacher/assignments.php');
    }

    $stmt = $pdo->prepare("INSERT INTO materials (course_id, title, category, file_path, video_link, file_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$courseId, $title, $category, $filePath, $videoLink ?: null, $fileType, $teacherId]);
    flash_set('success', 'Material published.');
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

$materialsStmt = $pdo->prepare("
    SELECT m.*, c.course_code, c.course_title
    FROM materials m
    JOIN courses c ON c.id = m.course_id
    WHERE m.created_by = ?
    ORDER BY m.created_at DESC
");
$materialsStmt->execute([$teacherId]);
$materials = $materialsStmt->fetchAll();
?>
<h1>Assignments & Materials</h1>
<p class="muted">Publish assessment details and upload learning resources with category tags.</p>

<div class="grid-2">
    <form class="panel" method="post" enctype="multipart/form-data">
        <h3 style="margin-top:0;">Publish Assignment</h3>
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

    <form class="panel" method="post" enctype="multipart/form-data">
        <h3 style="margin-top:0;">Upload Material</h3>
        <input type="hidden" name="upload_material" value="1">
        <div class="form-row">
            <label><span class="small">Course</span>
                <select class="input" name="course_id" required>
                    <option value="">Choose course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span class="small">Type</span>
                <select class="input" name="file_type" required>
                    <option value="">Select file type</option>
                    <option>PDF</option>
                    <option>PPTX</option>
                    <option>MP4</option>
                </select>
            </label>
        </div>
        <div class="form-row">
            <label><span class="small">Category</span>
                <select class="input" name="category" required>
                    <option>Lecture Notes</option>
                    <option>Lab Sheets</option>
                    <option>Reading Material</option>
                </select>
            </label>
            <label><span class="small">Title</span><input class="input" type="text" name="material_title" required></label>
        </div>
        <div class="form-row one">
            <label><span class="small">PDF/PPTX Upload</span><input class="input" type="file" name="material_file" accept=".pdf,.pptx"></label>
        </div>
        <div class="form-row one">
            <label><span class="small">MP4 Link</span><input class="input" type="url" name="video_link" placeholder="https://video-link..."></label>
        </div>
        <button class="btn secondary" type="submit">Publish Material</button>
    </form>
</div>

<div class="grid-2" style="margin-top:20px;">
    <div class="panel">
        <h3 style="margin-top:0;">Published Assignments</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Course</th><th>Title</th><th>Deadline</th></tr></thead>
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
    </div>
    <div class="panel">
        <h3 style="margin-top:0;">Published Materials</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Course</th><th>Title</th><th>Category</th></tr></thead>
                <tbody>
                    <?php foreach ($materials as $m): ?>
                        <tr>
                            <td><?= esc($m['course_code']) ?></td>
                            <td><?= esc($m['title']) ?></td>
                            <td><?= esc($m['category']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
