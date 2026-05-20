<?php
require_once __DIR__ . '/config.php';
require_role(['Academic Admin', 'Teacher']);

$user = current_user();
$role = $user['role'];
$uid  = (int)$user['id'];
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/chat.php');

$groupId = (int)($_POST['group_id'] ?? 0);
$member  = trim($_POST['member'] ?? '');

if (!$groupId || $member === '') {
    flash_set('error', 'Invalid request.');
    redirect('/chat_view.php?group_id=' . $groupId);
}

[$memberRole, $memberId] = array_pad(explode(':', $member, 2), 2, '');
$memberId = (int)$memberId;
if (!in_array($memberRole, ['Academic Admin', 'Teacher', 'Student'], true) || $memberId <= 0) {
    flash_set('error', 'Invalid member selected.');
    redirect('/chat_view.php?group_id=' . $groupId);
}

// Cannot remove yourself
if ($memberRole === $role && $memberId === $uid) {
    flash_set('error', 'You cannot remove yourself from the group.');
    redirect('/chat_view.php?group_id=' . $groupId);
}

$stmt = $pdo->prepare("SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_role = ? AND user_id = ?");
$stmt->execute([$groupId, $role, $uid]);
if (!$stmt->fetch()) {
    flash_set('error', 'Group not found or access denied.');
    redirect('/chat.php');
}

$delete = $pdo->prepare("DELETE FROM group_chat_members WHERE group_id = ? AND user_role = ? AND user_id = ?");
$delete->execute([$groupId, $memberRole, $memberId]);

flash_set('success', 'Member removed successfully.');
redirect('/chat_view.php?group_id=' . $groupId);
