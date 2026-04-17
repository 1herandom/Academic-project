<?php
// Include configuration file (DB connection, constants)
require_once __DIR__ . '/../config.php';

// Include authentication system
require_once __DIR__ . '/../includes/auth.php';

// Restrict access to only students
require_role('Student');

/*
|--------------------------------------------------------------------------
| Feature 2 | Suprim: Assignment file upload engine with deadline enforcement
| Feature 3 | Suprim: Automated time-fencing with server-side validation
|--------------------------------------------------------------------------
*/

// Initialize database connection
$pdo = db();

// Get logged-in student ID
$studentId = current_user()['id'];

// Directory for storing uploaded assignment files
$uploadDir = __DIR__ . '/../storage/uploads/assignments/';

// Check if form is submitted via POST and submit button is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {

    // Get assignment ID from form input
    $assignmentId = (int)$_POST['assignment_id'];

    // Fetch assignment details and verify student enrollment
    $stmt = $pdo->prepare("
        SELECT a.*, c.course_code, c.course_title
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        JOIN enrollments e ON e.course_id = c.id AND e.student_user_id = ?
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId, $assignmentId]);

    // Retrieve assignment record
    $assignment = $stmt->fetch();

    // If assignment is invalid or student not enrolled
    if (!$assignment) {
        flash_set('error', 'Assignment not found.');
        redirect('/student/submissions.php');
    }

    // Get current UTC time (server-side validation)
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    // Get assignment deadline
    $deadline = new DateTimeImmutable($assignment['deadline_at'], new DateTimeZone('UTC'));

    // Enforce deadline (with slight buffer of 1 minute)
    if ($now >= $deadline->modify('+1 minute')) {
        flash_set('error', 'Submission Closed.');
        redirect('/student/submissions.php');
    }

    // Check if file was uploaded
    if (empty($_FILES['submission_file']['tmp_name'])) {
        flash_set('error', 'Please upload a file.');
        redirect('/student/submissions.php?assignment_id=' . $assignmentId);
    }

    // Get uploaded file size
    $size = (int)$_FILES['submission_file']['size'];

    // Validate max file size (20MB)
    if ($size > 20 * 1024 * 1024) {
        flash_set('error', 'Maximum file size is 20 MB.');
        redirect('/student/submissions.php?assignment_id=' . $assignmentId);
    }

    // Extract and normalize file extension
    $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));

    // Allow only PDF and DOCX formats
    if (!in_array($ext, ['pdf','docx'], true)) {
        flash_set('error', 'Only PDF or DOCX files are accepted.');
        redirect('/student/submissions.php?assignment_id=' . $assignmentId);
    }

    // Get course code (used in filename)
    $courseCode = $assignment['course_code'];

    // Fetch student's institutional ID
    $studentInfo = $pdo->prepare("SELECT institutional_id FROM users WHERE id = ?");
    $studentInfo->execute([$studentId]);
    $institutionalId = $studentInfo->fetchColumn();

    // Generate timestamp for unique file naming
    $timestamp = gmdate('Ymd_His');

    // Create safe and unique filename
    $storedName = safe_filename($courseCode . '_' . $institutionalId . '_' . $timestamp . '.' . $ext);

    // Define full file path
    $targetPath = $uploadDir . $storedName;

    // Move uploaded file to storage directory
    if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $targetPath)) {
        flash_set('error', 'Upload failed.');
        redirect('/student/submissions.php?assignment_id=' . $assignmentId);
    }

    // Check if a submission already exists for this assignment
    $existing = $pdo->prepare("SELECT id, stored_filename FROM submissions WHERE assignment_id = ? AND student_user_id = ?");
    $existing->execute([$assignmentId, $studentId]);
    $existingRow = $existing->fetch();

    // If submission exists → replace it
    if ($existingRow) {

        // Locate old file
        $oldPath = $uploadDir . $existingRow['stored_filename'];

        // Delete old file if it exists
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }

        // Update submission record
        $upd = $pdo->prepare("
            UPDATE submissions 
            SET original_filename = ?, stored_filename = ?, mime_type = ?, file_size = ?, updated_at = UTC_TIMESTAMP() 
            WHERE id = ?
        ");
        $upd->execute([
            $_FILES['submission_file']['name'], // original filename
            $storedName,                        // new stored filename
            $_FILES['submission_file']['type'] ?: 'application/octet-stream', // MIME type
            $size,                              // file size
            $existingRow['id']                  // submission ID
        ]);

        flash_set('success', 'Submission replaced successfully.');

    } else {

        // Insert new submission record
        $ins = $pdo->prepare("
            INSERT INTO submissions 
            (assignment_id, student_user_id, original_filename, stored_filename, mime_type, file_size) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $assignmentId,
            $studentId,
            $_FILES['submission_file']['name'],
            $storedName,
            $_FILES['submission_file']['type'] ?: 'application/octet-stream',
            $size
        ]);

        flash_set('success', 'Submission received.');
    }

    // Redirect back to submissions page after processing
    redirect('/student/submissions.php?course_id=' . (int)$assignment['course_id']);
}