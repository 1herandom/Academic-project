<?php
require_once __DIR__ . '/config.php';
require_role(['Academic Admin', 'Teacher', 'Student']);

header('Content-Type: application/json');

$user = current_user();
$role = $user['role'];
$uid  = (int)$user['id'];
$pdo  = db();

$cid     = (int)($_GET['conversation_id'] ?? 0);
$gid     = (int)($_GET['group_id'] ?? 0);
$afterId = (int)($_GET['after_id'] ?? 0);

if (!$cid && !$gid) { echo json_encode(['messages' => []]); exit; }

$threadCol = $gid ? 'group_id' : 'conversation_id';
$threadId  = $gid ?: $cid;

// Verify access
if ($gid) {
    $stmt = $pdo->prepare("SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_role = ? AND user_id = ?");
    $stmt->execute([$gid, $role, $uid]);
} else {
    $stmt = $pdo->prepare("SELECT 1 FROM chat_conversations WHERE id = ? AND ((initiator_role=? AND initiator_id=?) OR (participant_role=? AND participant_id=?))");
    $stmt->execute([$cid, $role, $uid, $role, $uid]);
}

if (!$stmt->fetch()) { echo json_encode(['messages' => []]); exit; }

// Mark incoming messages as read (direct only for now)
if (!$gid) {
    $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_role != ? AND sender_id != ? AND is_read = 0")
        ->execute([$cid, $role, $uid]);
}

// Fetch only new messages
$msgs = $pdo->prepare("SELECT id, sender_role, sender_id, body, sent_at FROM chat_messages WHERE {$threadCol} = ? AND id > ? ORDER BY sent_at ASC");
$msgs->execute([$threadId, $afterId]);
$rows = $msgs->fetchAll();

// Attach sender name to each message
function resolve_name_poll(PDO $pdo, string $role, int $id): string {
    $table = match($role) {
        'Academic Admin' => 'admins',
        'Teacher'        => 'teachers',
        'Student'        => 'students',
        default          => 'admins'
    };
    $s = $pdo->prepare("SELECT first_name, last_name FROM {$table} WHERE id = ?");
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ? trim($r['first_name'] . ' ' . $r['last_name']) : 'Unknown';
}

$out = [];
foreach ($rows as $m) {
    $out[] = [
        'id'          => (int)$m['id'],
        'sender_role' => $m['sender_role'],
        'sender_id'   => (int)$m['sender_id'],
        'sender_name' => resolve_name_poll($pdo, $m['sender_role'], $m['sender_id']),
        'is_mine'     => $m['sender_role'] === $role && (int)$m['sender_id'] === $uid,
        'body'        => $m['body'],
        'sent_at'     => $m['sent_at'],
        'time_fmt'    => date('g:i A', strtotime($m['sent_at'])),
    ];
}

echo json_encode(['messages' => $out]);
