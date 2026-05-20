<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    
    $del = $pdo->prepare("DELETE FROM notices WHERE id = ? AND sender_role = 'Teacher' AND sender_id = ?");
    $del->execute([$id, $user['id']]);
    
    flash_set('success', 'Notice deleted successfully.');
}

redirect('/teacher/notices.php');
