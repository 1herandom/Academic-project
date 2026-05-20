<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

header('Content-Type: application/json');

$pdo      = db();
$courseId = (int)($_GET['course_id']    ?? 0);
$type     = $_GET['session_type'] ?? '';
$date     = $_GET['session_date'] ?? '';

if (!$courseId || !in_array($type, ['L','T','W'], true) || $date === '') {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendance_sessions
    WHERE course_id = ? AND session_type = ? AND session_date = ?
");
$stmt->execute([$courseId, $type, $date]);
echo json_encode(['exists' => (int)$stmt->fetchColumn() > 0]);
