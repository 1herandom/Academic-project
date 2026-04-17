<?php
// Include configuration (database connection, constants)
require_once __DIR__ . '/../config.php';

// Include authentication system
require_once __DIR__ . '/../includes/auth.php';

// Restrict access to Teacher role only
require_role('Teacher');

/*
|--------------------------------------------------------------------------
| Feature 1 | Suprim: Publish assignment details with brief file upload
| Feature 4 | Suprim: Upload categorized lecture slides and lab sheets
|--------------------------------------------------------------------------
*/

// Initialize database connection
$pdo = db();

// Get current logged-in teacher ID
$teacherId = current_user()['id'];

// Define upload directory for assignment briefs
$uploadDir = __DIR__ . '/../storage/uploads/briefs/';

// Fetch all courses assigned to this teacher
$courseStmt = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| ASSIGNMENT CREATION LOGIC
|--------------------------------------------------------------------------
*/

// Check if assignment form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {

    // Retrieve and sanitize form inputs
    $courseId = (int)$_POST['course_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadlineAt = trim($_POST['deadline_at'] ?? '');
    $subjectLink = trim($_POST['subject_link'] ?? '');

    // Validate required fields
    if ($courseId <= 0 || $title === '' || $description === '' || $deadlineAt === '') {
        flash_set('error', 'Please complete the assignment form.');
        redirect('/teacher/assignments.php');
    }

    // Initialize brief file path
    $briefFilePath = null;

    // Check if a brief file is uploaded
    if (!empty($_FILES['brief_file']['tmp_name'])) {

        // Get file extension
        $ext = strtolower(pathinfo($_FILES['brief_file']['name'], PATHINFO_EXTENSION));

        // Allow only PDF or DOCX
        if (!in_array($ext, ['pdf','docx'], true)) {
            flash_set('error', 'Brief file must be PDF or DOCX.');
            redirect('/teacher/assignments.php');
        }

        // Generate safe file name
        $name = safe_filename('brief_' . time() . '_' . $_FILES['brief_file']['name']);

        // Define file path for storage
        $briefFilePath = 'storage/uploads/briefs/' . $name;

        // Move uploaded file to storage
        move_uploaded_file($_FILES['brief_file']['tmp_name'], __DIR__ . '/../' . $briefFilePath);
    }

    // Insert assignment into database
    $stmt = $pdo->prepare("INSERT INTO assignments (course_id, title, description, deadline_at, subject_link, brief_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$courseId, $title, $description, $deadlineAt, $subjectLink ?: null, $briefFilePath, $teacherId]);

    // Show success message
    flash_set('success', 'Assignment published.');

    // Redirect back to page
    redirect('/teacher/assignments.php');
}

/*
|--------------------------------------------------------------------------
| MATERIAL UPLOAD LOGIC
|--------------------------------------------------------------------------
*/

// Check if material upload form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {

    // Retrieve form inputs
    $courseId = (int)$_POST['course_id'];
    $title = trim($_POST['material_title'] ?? '');
    $category = $_POST['category'] ?? '';
    $fileType = $_POST['file_type'] ?? '';
    $videoLink = trim($_POST['video_link'] ?? '');

    // Validate inputs
    if (
        $courseId <= 0 ||
        $title === '' ||
        !in_array($category, ['Lecture Notes','Lab Sheets','Reading Material'], true) ||
        !in_array($fileType, ['PDF','PPTX','MP4'], true)
    ) {
        flash_set('error', 'Please complete the material form.');
        redirect('/teacher/assignments.php');
    }

    $filePath = null;

    // Handle file upload for PDF/PPTX
    if (in_array($fileType, ['PDF','PPTX'], true)) {

        // Ensure file is uploaded
        if (empty($_FILES['material_file']['tmp_name'])) {
            flash_set('error', 'Please upload a file for PDF/PPTX material.');
            redirect('/teacher/assignments.php');
        }

        // Validate file extension
        $ext = strtolower(pathinfo($_FILES['material_file']['name'], PATHINFO_EXTENSION));
        if (($fileType === 'PDF' && $ext !== 'pdf') || ($fileType === 'PPTX' && $ext !== 'pptx')) {
            flash_set('error', 'File type does not match the selected material type.');
            redirect('/teacher/assignments.php');
        }

        // Generate safe filename
        $name = safe_filename('material_' . time() . '_' . $_FILES['material_file']['name']);

        // Define storage directory
        $dir = 'storage/uploads/materials/';

        // Final file path
        $filePath = $dir . $name;

        // Move uploaded file
        move_uploaded_file($_FILES['material_file']['tmp_name'], __DIR__ . '/../' . $filePath);
    }

    // Validate MP4 requires a link
    if ($fileType === 'MP4' && $videoLink === '') {
        flash_set('error', 'MP4 materials must include a link.');
        redirect('/teacher/assignments.php');
    }

    // Insert material into database
    $stmt = $pdo->prepare("INSERT INTO materials (course_id, title, category, file_path, video_link, file_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$courseId, $title, $category, $filePath, $videoLink ?: null, $fileType, $teacherId]);

    // Success message
    flash_set('success', 'Material published.');

    // Redirect
    redirect('/teacher/assignments.php');
}

/*
|--------------------------------------------------------------------------
| FETCH DATA FOR DISPLAY
|--------------------------------------------------------------------------
*/

// Fetch assignments created by this teacher
$assignments = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_title
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    WHERE a.created_by = ?
    ORDER BY a.deadline_at DESC
");
$assignments->execute([$teacherId]);
$assignments = $assignments->fetchAll();

// Fetch materials created by this teacher
$materialsStmt = $pdo->prepare("
    SELECT m.*, c.course_code, c.course_title
    FROM materials m
    JOIN courses c ON c.id = m.course_id
    WHERE m.created_by = ?
    ORDER BY m.created_at DESC
");
$materialsStmt->execute([$teacherId]);
$materials = $materialsStmt->fetchAll();

// All POST requests handled — now safe to render HTML
require_once __DIR__ . '/../includes/header.php';
?>