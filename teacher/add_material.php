<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$pdo = db();
$teacherId = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $courseId = (int)$_POST['course_id'];
    $title = trim($_POST['material_title'] ?? '');
    $category = $_POST['category'] ?? '';
    $fileType = $_POST['file_type'] ?? '';
    $videoLink = trim($_POST['video_link'] ?? '');

    if ($courseId <= 0 || $title === '' || !in_array($category, ['Lecture Notes','Lab Sheets','Reading Material'], true) || !in_array($fileType, ['PDF','PPTX','MP4'], true)) {
        flash_set('error', 'Please complete the material form.');
        redirect('/teacher/add_material.php');
    }

    $filePath = null;
    $uploadedFile = $_FILES['material_file'] ?? null;
    $hasUploadedFile = $uploadedFile && !empty($uploadedFile['tmp_name']);

    if (in_array($fileType, ['PDF','PPTX','MP4'], true)) {
        if ($hasUploadedFile) {
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                flash_set('error', 'File upload failed. Please try a smaller file or retry.');
                redirect('/teacher/add_material.php');
            }

            $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $allowed = [
                'PDF'  => 'pdf',
                'PPTX' => 'pptx',
                'MP4'  => 'mp4',
            ];

            if ($ext !== $allowed[$fileType]) {
                flash_set('error', 'File type does not match the selected material type.');
                redirect('/teacher/add_material.php');
            }

            $name = safe_filename('material_' . time() . '_' . $uploadedFile['name']);
            $dir = 'storage/uploads/materials/';
            $absDir = __DIR__ . '/../' . $dir;
            if (!is_dir($absDir)) {
                mkdir($absDir, 0775, true);
                @chmod($absDir, 0775);
            }
            if (!is_writable($absDir)) {
                @chmod($absDir, 0775);
            }
            if (!is_writable($absDir)) {
                flash_set('error', 'Unable to save the uploaded file. Please check the uploads directory permissions.');
                redirect('/teacher/add_material.php');
            }

            $filePath = $dir . $name;
            if (!move_uploaded_file($uploadedFile['tmp_name'], $absDir . $name)) {
                flash_set('error', 'Failed to save the uploaded file. Please try again.');
                redirect('/teacher/add_material.php');
            }
        } elseif (in_array($fileType, ['PDF','PPTX'], true)) {
            flash_set('error', 'Please upload a file for PDF/PPTX material.');
            redirect('/teacher/add_material.php');
        }
    }

    if ($fileType === 'MP4' && $videoLink === '' && $filePath === null) {
        flash_set('error', 'MP4 materials must include either a video file or a video link.');
        redirect('/teacher/add_material.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO materials (course_id, title, category, file_path, video_link, file_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$courseId, $title, $category, $filePath, $videoLink ?: null, $fileType, $teacherId]);
        flash_set('success', 'Material published.');
        redirect('/teacher/add_material.php');
    } catch (Exception $e) {
        // Clean up uploaded file if database insert fails
        if ($filePath && file_exists(__DIR__ . '/../' . $filePath)) {
            unlink(__DIR__ . '/../' . $filePath);
        }
        flash_set('error', 'Failed to save material to database: ' . $e->getMessage());
        redirect('/teacher/add_material.php');
    }
}

$courseStmt = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Upload Material</h1>
        <p class="muted page-subtitle">Upload learning resources with category tags.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/teacher/materials.php" class="btn secondary">Back to List</a>
</div>

<form class="panel" method="post" enctype="multipart/form-data">
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
        <label><span class="small">File Upload</span><input class="input" type="file" name="material_file" accept=".pdf,.pptx,.mp4"></label>
        <small class="muted">Upload PDF/PPTX files or an MP4 video file. For MP4, you may also provide a link.</small>
    </div>
    <div class="form-row one">
        <label><span class="small">MP4 Link</span><input class="input" type="url" name="video_link" placeholder="https://video-link..."></label>
    </div>
    <button class="btn" type="submit">Publish Material</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
