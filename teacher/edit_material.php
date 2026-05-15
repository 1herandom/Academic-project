<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user      = current_user();
$pdo       = db();
$teacherId = $user['id'];

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

// Fetch the material — ownership check baked in
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ? AND created_by = ?");
$stmt->execute([$id, $teacherId]);
$material = $stmt->fetch();

if (!$material) {
    flash_set('error', 'Material not found or permission denied.');
    redirect('/teacher/materials.php');
}

// Courses taught by this teacher
$courseStmt = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId   = (int)($_POST['course_id'] ?? 0);
    $title      = trim($_POST['material_title'] ?? '');
    $category   = $_POST['category'] ?? '';
    $fileType   = $_POST['file_type'] ?? '';
    $videoLink  = trim($_POST['video_link'] ?? '');

    if ($courseId <= 0 || $title === ''
        || !in_array($category, ['Lecture Notes', 'Lab Sheets', 'Reading Material'], true)
        || !in_array($fileType, ['PDF', 'PPTX', 'MP4'], true)) {
        flash_set('error', 'Please complete all required fields.');
        redirect("/teacher/edit_material.php?id=$id");
    }

    $newFilePath = $material['file_path']; // keep existing by default
    $uploadedFile = $_FILES['material_file'] ?? null;
    $hasNewFile   = $uploadedFile && !empty($uploadedFile['tmp_name']) && $uploadedFile['error'] === UPLOAD_ERR_OK;

    if ($hasNewFile) {
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $allowed = ['PDF' => 'pdf', 'PPTX' => 'pptx', 'MP4' => 'mp4'];

        if ($ext !== $allowed[$fileType]) {
            flash_set('error', 'File type does not match the selected material type.');
            redirect("/teacher/edit_material.php?id=$id");
        }

        $name   = safe_filename('material_' . time() . '_' . $uploadedFile['name']);
        $dir    = 'storage/uploads/materials/';
        $absDir = __DIR__ . '/../' . $dir;

        if (!is_dir($absDir)) {
            mkdir($absDir, 0775, true);
        }
        if (!is_writable($absDir)) {
            @chmod($absDir, 0775);
        }

        if (!move_uploaded_file($uploadedFile['tmp_name'], $absDir . $name)) {
            flash_set('error', 'Failed to save the new file. Please try again.');
            redirect("/teacher/edit_material.php?id=$id");
        }

        // Remove old file from disk
        if (!empty($material['file_path'])) {
            $oldAbs = __DIR__ . '/../' . $material['file_path'];
            if (file_exists($oldAbs)) {
                @unlink($oldAbs);
            }
        }

        $newFilePath = $dir . $name;
    }

    // For MP4 with no file and no link
    if ($fileType === 'MP4' && $newFilePath === null && $videoLink === '') {
        flash_set('error', 'MP4 materials must include either a video file or a video link.');
        redirect("/teacher/edit_material.php?id=$id");
    }

    $upd = $pdo->prepare("UPDATE materials SET course_id=?, title=?, category=?, file_path=?, video_link=?, file_type=? WHERE id=? AND created_by=?");
    $upd->execute([$courseId, $title, $category, $newFilePath, $videoLink ?: null, $fileType, $id, $teacherId]);

    flash_set('success', 'Material updated successfully.');
    redirect('/teacher/materials.php');
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Edit Material</h1>
        <p class="muted page-subtitle">Update the details for this learning resource.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/teacher/materials.php" class="btn secondary">Back to List</a>
</div>

<form class="panel" method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="form-row">
        <label><span class="small">Course</span>
            <select class="input" name="course_id" required>
                <option value="">Choose course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $material['course_id'] == $c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['course_code'] . ' - ' . $c['course_title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span class="small">Type</span>
            <select class="input" name="file_type" required>
                <option value="">Select file type</option>
                <?php foreach (['PDF','PPTX','MP4'] as $ft): ?>
                    <option <?= $material['file_type'] === $ft ? 'selected' : '' ?>><?= $ft ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="form-row">
        <label><span class="small">Category</span>
            <select class="input" name="category" required>
                <?php foreach (['Lecture Notes','Lab Sheets','Reading Material'] as $cat): ?>
                    <option <?= $material['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span class="small">Title</span>
            <input class="input" type="text" name="material_title" value="<?= esc($material['title']) ?>" required>
        </label>
    </div>

    <?php if (!empty($material['file_path'])): ?>
    <div class="form-row one">
        <p class="small muted">Current file: <strong><?= esc(basename($material['file_path'])) ?></strong>
            — upload a new file below to replace it, or leave empty to keep it.</p>
    </div>
    <?php endif; ?>

    <div class="form-row one">
        <label><span class="small">Replace File (optional)</span>
            <input class="input" type="file" name="material_file" accept=".pdf,.pptx,.mp4">
        </label>
        <small class="muted">Upload a new PDF/PPTX/MP4 to replace the existing file.</small>
    </div>

    <div class="form-row one">
        <label><span class="small">MP4 Link</span>
            <input class="input" type="url" name="video_link" value="<?= esc($material['video_link'] ?? '') ?>" placeholder="https://video-link...">
        </label>
    </div>

    <button class="btn" type="submit">Save Changes</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
