<?php
require_once __DIR__ . '/config.php';
require_role(['Academic Admin', 'Teacher']); // Students cannot initiate

$user = current_user();
$role = $user['role'];
$uid  = (int)$user['id'];
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $participantRole = trim($_GET['role'] ?? '');
    $participantId   = (int)($_GET['id'] ?? 0);
    
    // Validate recipient role
    $allowedRoles = ['Academic Admin' => ['Academic Admin', 'Teacher', 'Student'], 'Teacher' => ['Academic Admin', 'Teacher', 'Student']];
    if (!in_array($participantRole, $allowedRoles[$role] ?? [], true) || !$participantId) {
        flash_set('error', 'Invalid request.');
        redirect('/chat.php');
    }
    
    $recipientTable = match($participantRole) {
        'Academic Admin' => 'admins',
        'Teacher' => 'teachers',
        'Student'  => 'students',
        default    => null
    };
    
    if (!$recipientTable) {
        flash_set('error', 'Invalid recipient type.');
        redirect('/chat.php');
    }
    
    $check = $pdo->prepare("SELECT id FROM {$recipientTable} WHERE id = ? AND status = 'active'");
    $check->execute([$participantId]);
    if (!$check->fetch()) {
        flash_set('error', 'Recipient not found or inactive.');
        redirect('/chat.php');
    }

    if ($role === 'Teacher' && $participantRole === 'Student') {
        $enrolled = $pdo->prepare("
            SELECT COUNT(*) FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            WHERE c.teacher_user_id = ? AND e.student_user_id = ?
        ");
        $enrolled->execute([$uid, $participantId]);
        if ((int)$enrolled->fetchColumn() === 0) {
            flash_set('error', 'You can only message students enrolled in your courses.');
            redirect('/chat.php');
        }
    }

    $existing = $pdo->prepare("
        SELECT id FROM chat_conversations
        WHERE (initiator_role = ? AND initiator_id = ? AND participant_role = ? AND participant_id = ?)
           OR (initiator_role = ? AND initiator_id = ? AND participant_role = ? AND participant_id = ?)
        LIMIT 1
    ");
    $existing->execute([
        $role, $uid, $participantRole, $participantId,
        $participantRole, $participantId, $role, $uid
    ]);
    $convo = $existing->fetch();

    if ($convo) {
        redirect('/chat_view.php?id=' . $convo['id']);
    } else {
        $ins = $pdo->prepare("INSERT INTO chat_conversations (initiator_role, initiator_id, participant_role, participant_id) VALUES (?,?,?,?)");
        $ins->execute([$role, $uid, $participantRole, $participantId]);
        $cid = (int)$pdo->lastInsertId();
        redirect('/chat_view.php?id=' . $cid);
    }
}

// ── Handle POST (from modal) ──
$participantRole = trim($_POST['participant_role'] ?? '');
$participantId   = (int)($_POST['participant_id'] ?? 0);
$firstMessage    = trim($_POST['first_message'] ?? '');

// Validate recipient role
$allowedRoles = ['Academic Admin' => ['Academic Admin', 'Teacher', 'Student'], 'Teacher' => ['Academic Admin', 'Teacher', 'Student']];
if (!in_array($participantRole, $allowedRoles[$role] ?? [], true) || !$participantId || !$firstMessage) {
    flash_set('error', 'Invalid request.');
    redirect('/chat.php');
}

// Validate recipient exists and is active
$recipientTable = match($participantRole) {
    'Academic Admin' => 'admins',
    'Teacher' => 'teachers',
    'Student'  => 'students',
    default    => null
};

if (!$recipientTable) {
    flash_set('error', 'Invalid recipient type.');
    redirect('/chat.php');
}

$check = $pdo->prepare("SELECT id FROM {$recipientTable} WHERE id = ? AND status = 'active'");
$check->execute([$participantId]);
if (!$check->fetch()) {
    flash_set('error', 'Recipient not found or inactive.');
    redirect('/chat.php');
}

// For Teacher initiating: ensure student is enrolled in their course (not needed for other roles)
if ($role === 'Teacher' && $participantRole === 'Student') {
    $enrolled = $pdo->prepare("
        SELECT COUNT(*) FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        WHERE c.teacher_user_id = ? AND e.student_user_id = ?
    ");
    $enrolled->execute([$uid, $participantId]);
    if ((int)$enrolled->fetchColumn() === 0) {
        flash_set('error', 'You can only message students enrolled in your courses.');
        redirect('/chat.php');
    }
}

// Check if a conversation already exists (either direction)
$existing = $pdo->prepare("
    SELECT id FROM chat_conversations
    WHERE (initiator_role = ? AND initiator_id = ? AND participant_role = ? AND participant_id = ?)
       OR (initiator_role = ? AND initiator_id = ? AND participant_role = ? AND participant_id = ?)
    LIMIT 1
");
$existing->execute([
    $role, $uid, $participantRole, $participantId,
    $participantRole, $participantId, $role, $uid
]);
$convo = $existing->fetch();

if ($convo) {
    $cid = (int)$convo['id'];
} else {
    // Create new conversation
    $ins = $pdo->prepare("INSERT INTO chat_conversations (initiator_role, initiator_id, participant_role, participant_id) VALUES (?,?,?,?)");
    $ins->execute([$role, $uid, $participantRole, $participantId]);
    $cid = (int)$pdo->lastInsertId();
}

// Insert the first message
$msg = $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_role, sender_id, body) VALUES (?,?,?,?)");
$msg->execute([$cid, $role, $uid, $firstMessage]);

// Update conversation timestamp
$pdo->prepare("UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$cid]);

redirect('/chat_view.php?id=' . $cid);
