<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user      = current_user();
$pdo       = db();
$teacherId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/teacher/materials.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid material ID.');
    redirect('/teacher/materials.php');
}

// Fetch the record — ownership check baked in
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ? AND created_by = ?");
$stmt->execute([$id, $teacherId]);
$material = $stmt->fetch();

if (!$material) {
    flash_set('error', 'Material not found or permission denied.');
    redirect('/teacher/materials.php');
}

// Remove physical file from server if it exists
if (!empty($material['file_path'])) {
    $absPath = __DIR__ . '/../' . $material['file_path'];
    if (file_exists($absPath)) {
        @unlink($absPath);
    }
}

// Remove DB record
$del = $pdo->prepare("DELETE FROM materials WHERE id = ? AND created_by = ?");
$del->execute([$id, $teacherId]);

flash_set('success', 'Material deleted successfully.');
redirect('/teacher/materials.php');
