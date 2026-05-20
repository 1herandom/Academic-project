<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

header('Content-Type: application/json');

$pdo       = db();
$teacherId = current_user()['id'];
$courseId  = (int)($_GET['course_id']    ?? 0);
$type      = $_GET['session_type'] ?? '';
$date      = $_GET['session_date'] ?? '';

if (!$courseId || !in_array($type, ['L','T','W'], true) || $date === '') {
    echo json_encode(['exists' => false]);
    exit;
}

// Only return true if the teacher owns this course
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendance_sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE s.course_id = ? AND s.session_type = ? AND s.session_date = ?
      AND (s.teacher_user_id = ? OR c.teacher_user_id = ?)
");
$stmt->execute([$courseId, $type, $date, $teacherId, $teacherId]);
echo json_encode(['exists' => (int)$stmt->fetchColumn() > 0]);
