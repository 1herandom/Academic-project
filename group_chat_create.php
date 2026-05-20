<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_role(['Academic Admin', 'Teacher']);

$user = current_user();
$role = $user['role'];
$uid  = (int)$user['id'];
$pdo  = db();

// ── GET: redirect to chat.php with group tab + course pre-selected ─────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $url = APP_BASE_URL . '/chat.php?compose=group';
    if ($courseId > 0) $url .= '&course_id=' . $courseId;
    header('Location: ' . $url);
    exit;
}

// ── POST: create the group chat ────────────────────────────────────────────
$groupName    = trim($_POST['group_name']    ?? '');
$participants = $_POST['participants']       ?? [];
$firstMessage = trim($_POST['first_message'] ?? '');

if (!$groupName || empty($participants) || !$firstMessage) {
    flash_set('error', 'Please provide a group name, participants, and an opening message.');
    redirect('/chat.php');
}

try {
    $pdo->beginTransaction();

    // 1. Create the group
    $ins = $pdo->prepare("INSERT INTO group_chats (name, creator_role, creator_id) VALUES (?,?,?)");
    $ins->execute([$groupName, $role, $uid]);
    $groupId = (int)$pdo->lastInsertId();

    // 2. Add creator as member
    $insMember = $pdo->prepare("INSERT INTO group_chat_members (group_id, user_role, user_id) VALUES (?,?,?)");
    $insMember->execute([$groupId, $role, $uid]);

    // 3. Add selected participants
    foreach ($participants as $p) {
        $parts = explode(':', $p, 2);
        if (count($parts) !== 2) continue;
        [$pRole, $pId] = $parts;
        $pId = (int)$pId;
        if (!in_array($pRole, ['Academic Admin', 'Teacher', 'Student'], true) || $pId <= 0) continue;
        if ($pRole === $role && $pId === $uid) continue; // skip creator duplicate
        $insMember->execute([$groupId, $pRole, $pId]);
    }

    // 4. Insert opening message
    $msg = $pdo->prepare("INSERT INTO chat_messages (group_id, sender_role, sender_id, body) VALUES (?,?,?,?)");
    $msg->execute([$groupId, $role, $uid, $firstMessage]);

    $pdo->commit();
    flash_set('success', 'Group chat created successfully!');
    redirect('/chat_view.php?group_id=' . $groupId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('error', 'Failed to create group chat: ' . $e->getMessage());
    redirect('/chat.php');
}
