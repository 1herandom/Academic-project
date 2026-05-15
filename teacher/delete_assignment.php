<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user      = current_user();
$pdo       = db();
$teacherId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/teacher/assignments.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid assignment ID.');
    redirect('/teacher/assignments.php');
}

// Fetch the record — ownership check baked in
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND created_by = ?");
$stmt->execute([$id, $teacherId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    flash_set('error', 'Assignment not found or permission denied.');
    redirect('/teacher/assignments.php');
}

// Remove physical brief file if it exists
if (!empty($assignment['brief_file'])) {
    $absPath = __DIR__ . '/../' . $assignment['brief_file'];
    if (file_exists($absPath)) {
        @unlink($absPath);
    }
}

// Remove DB record (submissions cascade automatically via FK ON DELETE CASCADE)
$del = $pdo->prepare("DELETE FROM assignments WHERE id = ? AND created_by = ?");
$del->execute([$id, $teacherId]);

flash_set('success', 'Assignment and all related submissions deleted.');
redirect('/teacher/assignments.php');
