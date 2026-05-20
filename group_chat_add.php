<?php
require_once __DIR__ . '/config.php';
require_role(['Academic Admin', 'Teacher']);

$user = current_user();
$role = $user['role'];
$uid  = (int)$user['id'];
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/chat.php');

$groupId      = (int)($_POST['group_id'] ?? 0);
$participants = $_POST['participants'] ?? [];

if (!$groupId || empty($participants)) {
    flash_set('error', 'Please select one or more members to add.');
    redirect('/chat_view.php?group_id=' . $groupId);
}

$stmt = $pdo->prepare("SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_role = ? AND user_id = ?");
$stmt->execute([$groupId, $role, $uid]);
if (!$stmt->fetch()) {
    flash_set('error', 'Group not found or access denied.');
    redirect('/chat.php');
}

$pdo->beginTransaction();
try {
    $ins = $pdo->prepare("INSERT INTO group_chat_members (group_id, user_role, user_id) VALUES (?,?,?)");
    $check = $pdo->prepare("SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_role = ? AND user_id = ?");

    $validateStudent = $pdo->prepare(
        "SELECT COUNT(*) FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE c.teacher_user_id = ? AND e.student_user_id = ?"
    );

    foreach ($participants as $participant) {
        $parts = explode(':', $participant, 2);
        if (count($parts) !== 2) continue;
        [$participantRole, $participantId] = $parts;
        $participantId = (int)$participantId;

        if ($participantId <= 0 || !in_array($participantRole, ['Academic Admin', 'Teacher', 'Student'], true)) {
            continue;
        }

        $check->execute([$groupId, $participantRole, $participantId]);
        if ($check->fetch()) {
            continue;
        }

        if ($role === 'Teacher' && $participantRole === 'Student') {
            $validateStudent->execute([$uid, $participantId]);
            if ((int)$validateStudent->fetchColumn() === 0) {
                continue;
            }
        }

        $ins->execute([$groupId, $participantRole, $participantId]);
    }

    $pdo->commit();
    flash_set('success', 'Members were added successfully.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('error', 'Unable to add members: ' . $e->getMessage());
}

redirect('/chat_view.php?group_id=' . $groupId);
