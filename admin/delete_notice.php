<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    
    $del = db()->prepare("DELETE FROM notices WHERE id = ? AND sender_role = 'Admin' AND sender_id = ?");
    $del->execute([$id, $user['id']]);
    
    flash_set('success', 'Notice deleted successfully.');
}

redirect('/admin/notices.php');
