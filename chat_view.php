<?php
require_once __DIR__ . '/config.php';
require_role(['Academic Admin', 'Teacher', 'Student']);

$user = current_user();
$role = $user['role'];
$uid  = (int)$user['id'];
$pdo  = db();

$cid = (int)($_GET['id'] ?? 0);
$gid = (int)($_GET['group_id'] ?? 0);

if (!$cid && !$gid) redirect('/chat.php');

$isGroup = ($gid > 0);
$convo = null;
$otherName = '';
$otherRole = '';

function resolve_user_display_name(PDO $pdo, string $role, int $id): string {
    $table = match($role) {
        'Academic Admin' => 'admins',
        'Teacher'        => 'teachers',
        'Student'        => 'students',
        default          => 'admins'
    };
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? trim($row['first_name'] . ' ' . $row['last_name']) : 'Unknown';
}

if ($isGroup) {
    // ── Load group and verify access ──────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT g.* FROM group_chats g 
        JOIN group_chat_members m ON m.group_id = g.id 
        WHERE g.id = ? AND m.user_role = ? AND m.user_id = ?
    ");
    $stmt->execute([$gid, $role, $uid]);
    $convo = $stmt->fetch();
    
    if (!$convo) {
        flash_set('error', 'Group not found or access denied.');
        redirect('/chat.php');
    }
    
    $otherName = $convo['name'];
    $otherRole = 'Group Chat';
    $threadCol = 'group_id';
    $threadId  = $gid;

    $memberStmt = $pdo->prepare("SELECT user_role, user_id FROM group_chat_members WHERE group_id = ?");
    $memberStmt->execute([$gid]);
    $members = $memberStmt->fetchAll();
    $groupMembers = [];
    $existingGroupMembers = [];
    foreach ($members as $member) {
        $groupMembers[] = [
            'role' => $member['user_role'],
            'id' => (int)$member['user_id'],
            'name' => resolve_user_display_name($pdo, $member['user_role'], (int)$member['user_id'])
        ];
        $existingGroupMembers[] = $member['user_role'] . ':' . $member['user_id'];
    }

    if (in_array($role, ['Academic Admin', 'Teacher'], true)) {
        $teachers = $pdo->query("SELECT id, first_name, last_name FROM teachers WHERE status='active' ORDER BY first_name")->fetchAll();
        if ($role === 'Teacher') {
            $teachers = array_filter($teachers, fn($t) => (int)$t['id'] !== $uid);
        }

        if ($role === 'Academic Admin') {
            $students = $pdo->query(
                "SELECT s.id, s.first_name, s.last_name, c.id AS course_id, c.course_code, c.course_title
                 FROM students s
                 LEFT JOIN enrollments e ON e.student_user_id = s.id
                 LEFT JOIN courses c ON c.id = e.course_id
                 WHERE s.status = 'active'
                 ORDER BY s.first_name"
            )->fetchAll();
            $courses  = $pdo->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code")->fetchAll();
        } else {
            $students = $pdo->prepare(
                "SELECT s.id, s.first_name, s.last_name, c.id AS course_id, c.course_code, c.course_title
                 FROM students s
                 JOIN enrollments e ON e.student_user_id = s.id
                 JOIN courses c ON c.id = e.course_id
                 WHERE c.teacher_user_id = ? AND s.status = 'active'
                 ORDER BY s.first_name"
            );
            $students->execute([$uid]);
            $students = $students->fetchAll();

            $courses = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
            $courses->execute([$uid]);
            $courses = $courses->fetchAll();
        }

        $studentMap = [];
        foreach ($students as $row) {
            $id = (int)$row['id'];
            if (!isset($studentMap[$id])) {
                $studentMap[$id] = [
                    'id' => $id,
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'courseIds' => [],
                    'courseNames' => []
                ];
            }
            if (!empty($row['course_id'])) {
                $courseId = (int)$row['course_id'];
                if (!in_array($courseId, $studentMap[$id]['courseIds'], true)) {
                    $studentMap[$id]['courseIds'][] = $courseId;
                    $studentMap[$id]['courseNames'][] = trim($row['course_code'] . ' ' . $row['course_title']);
                }
            }
        }
        $students = array_values($studentMap);
    }
} else {
    // ── Load conversation and verify access ──────────────────────────────────────
    $stmt = $pdo->prepare("SELECT * FROM chat_conversations WHERE id = ?");
    $stmt->execute([$cid]);
    $convo = $stmt->fetch();

    if (!$convo) {
        flash_set('error', 'Conversation not found.');
        redirect('/chat.php');
    }

    $isInitiator   = $convo['initiator_role']   === $role && (int)$convo['initiator_id']   === $uid;
    $isParticipant = $convo['participant_role'] === $role && (int)$convo['participant_id'] === $uid;

    if (!$isInitiator && !$isParticipant) {
        flash_set('error', 'Access denied.');
        redirect('/chat.php');
    }

    if ($isInitiator) {
        $tr = $convo['participant_role'];
        $ti = (int)$convo['participant_id'];
    } else {
        $tr = $convo['initiator_role'];
        $ti = (int)$convo['initiator_id'];
    }
    
    $otherName = resolve_user_display_name($pdo, $tr, $ti);
    $otherRole = $tr;
    $threadCol = 'conversation_id';
    $threadId  = $cid;
}

// ── Handle POST (send message) ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');
    $isAjax = !empty($_POST['ajax']);
    if ($body !== '') {
        $ins = $pdo->prepare("INSERT INTO chat_messages ({$threadCol}, sender_role, sender_id, body) VALUES (?,?,?,?)");
        $ins->execute([$threadId, $role, $uid, $body]);
        $newId = (int)$pdo->lastInsertId();
        
        if ($isGroup) {
            $pdo->prepare("UPDATE group_chats SET updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$gid]);
        } else {
            $pdo->prepare("UPDATE chat_conversations SET updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$cid]);
        }
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['id' => $newId]);
            exit;
        }
    } elseif ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['id' => 0]);
        exit;
    }
    $redirect = $isGroup ? "/chat_view.php?group_id={$gid}" : "/chat_view.php?id={$cid}";
    redirect($redirect);
}

// ── Mark messages as read ───────────────────────────────────────────────────
if ($isGroup) {
    $pdo->prepare("UPDATE group_chat_members SET last_read_at = CURRENT_TIMESTAMP WHERE group_id = ? AND user_role = ? AND user_id = ?")
        ->execute([$gid, $role, $uid]);
} else {
    $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_role != ? AND sender_id != ? AND is_read = 0")
        ->execute([$cid, $role, $uid]);
}

// ── Load messages ────────────────────────────────────────────────────────────
$msgs = $pdo->prepare("SELECT * FROM chat_messages WHERE {$threadCol} = ? ORDER BY sent_at ASC");
$msgs->execute([$threadId]);
$messages = $msgs->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/chat.css">

<div class="chat-layout chat-layout--active">

    <!-- ── Sidebar: unified conversation list ── -->
    <aside class="chat-sidebar chat-sidebar--slim">
        <?php
        // 1. Fetch direct conversations
        $convos = $pdo->prepare("
            SELECT c.id,
                   c.initiator_role, c.initiator_id,
                   c.participant_role, c.participant_id,
                   m.body AS last_msg,
                   m.sent_at AS last_sent,
                   (SELECT COUNT(*) FROM chat_messages cm2
                    WHERE cm2.conversation_id = c.id AND cm2.is_read = 0
                      AND cm2.sender_role != ? AND cm2.sender_id != ?) AS unread_count
            FROM chat_conversations c
            LEFT JOIN chat_messages m ON m.id = (
                SELECT id FROM chat_messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1
            )
            WHERE (c.initiator_role = ? AND c.initiator_id = ?)
               OR (c.participant_role = ? AND c.participant_id = ?)
        ");
        $convos->execute([$role, $uid, $role, $uid, $role, $uid]);
        $directList = $convos->fetchAll();

        // 2. Fetch group chats
        $groupsStmt = $pdo->prepare("
            SELECT g.id, g.name, 
                   m.body AS last_msg, 
                   m.sent_at AS last_sent
            FROM group_chats g
            JOIN group_chat_members gm ON gm.group_id = g.id
            LEFT JOIN chat_messages m ON m.id = (
                SELECT id FROM chat_messages WHERE group_id = g.id ORDER BY sent_at DESC LIMIT 1
            )
            WHERE gm.user_role = ? AND gm.user_id = ?
        ");
        $groupsStmt->execute([$role, $uid]);
        $groupList = $groupsStmt->fetchAll();

        // 3. Unify
        $sidebarList = [];
        foreach ($directList as $cv) {
            if ($cv['initiator_role'] === $role && (int)$cv['initiator_id'] === $uid) {
                $cvOtherRole = $cv['participant_role'];
                $cvOtherId   = (int)$cv['participant_id'];
            } else {
                $cvOtherRole = $cv['initiator_role'];
                $cvOtherId   = (int)$cv['initiator_id'];
            }
            $cvName = resolve_user_display_name($pdo, $cvOtherRole, $cvOtherId);
            $sidebarList[] = [
                'id'           => $cv['id'],
                'type'         => 'direct',
                'display_name' => $cvName,
                'last_msg'     => $cv['last_msg'],
                'last_sent'    => $cv['last_sent'],
                'unread_count' => $cv['unread_count']
            ];
        }
        foreach ($groupList as $gv) {
            $gUnreadStmt = $pdo->prepare("
                SELECT COUNT(*) FROM chat_messages cm
                JOIN group_chat_members gm ON gm.group_id = cm.group_id
                WHERE gm.group_id = ? AND gm.user_role = ? AND gm.user_id = ?
                  AND cm.sent_at > gm.last_read_at
                  AND (cm.sender_role != ? OR cm.sender_id != ?)
            ");
            $gUnreadStmt->execute([$gv['id'], $role, $uid, $role, $uid]);
            $gUnreadCount = (int)$gUnreadStmt->fetchColumn();

            $sidebarList[] = [
                'id'           => $gv['id'],
                'type'         => 'group',
                'display_name' => $gv['name'],
                'last_msg'     => $gv['last_msg'],
                'last_sent'    => $gv['last_sent'],
                'unread_count' => $gUnreadCount
            ];
        }
        usort($sidebarList, fn($a, $b) => strcmp($b['last_sent'] ?? '0', $a['last_sent'] ?? '0'));

        foreach ($sidebarList as $s):
            $active = ($s['type'] === 'direct' && $s['id'] === $cid) || ($s['type'] === 'group' && $s['id'] === $gid) ? 'active' : '';
            $link = $s['type'] === 'group' ? "chat_view.php?group_id=".$s['id'] : "chat_view.php?id=".$s['id'];
            $sIsGroup = $s['type'] === 'group';
        ?>
        <a class="chat-thread-item <?= $active ?>" href="<?= APP_BASE_URL ?>/<?= $link ?>">
            <div class="chat-avatar" style="<?= $sIsGroup ? 'background:linear-gradient(135deg, var(--herald-red), #a855f7); color:white;' : '' ?>">
                <?= strtoupper(substr($s['display_name'], 0, 1)) ?>
            </div>
            <div class="chat-thread-meta">
                <div class="chat-thread-top">
                    <span class="chat-thread-name"><?= esc($s['display_name']) ?></span>
                    <span class="chat-thread-time"><?= $s['last_sent'] ? date('M j', strtotime($s['last_sent'])) : '' ?></span>
                </div>
                <div class="chat-thread-bottom">
                    <span class="chat-thread-preview"><?= esc(mb_strimwidth($s['last_msg'] ?? '', 0, 40, '…')) ?></span>
                    <?php if ($s['unread_count'] > 0): ?>
                    <span class="chat-badge"><?= (int)$s['unread_count'] ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($sIsGroup): ?><span class="chat-role-tag">Group</span><?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </aside>

    <!-- ── Chat main area ── -->
    <div class="chat-main" style="position:relative; overflow:hidden;">
        <!-- Header -->
        <div class="chat-msg-header">
            <a href="<?= APP_BASE_URL ?>/chat.php" class="btn secondary sm" style="padding:6px 10px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="chat-avatar" style="width:36px;height:36px;font-size:.85rem;"><?= strtoupper(substr($otherName, 0, 1)) ?></div>
            <div>
                <div style="font-weight:700;font-size:.95rem;"><?= esc($otherName) ?></div>
                <div class="muted" style="font-size:.75rem;"><?= esc($otherRole) ?></div>
            </div>
            <?php if ($isGroup): ?>
            <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                <button type="button" class="btn secondary sm" onclick="toggleMembersPanel()" id="members-toggle-btn" style="padding:6px 12px; display:flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Members <span id="member-count-badge" style="background:var(--border-color); border-radius:999px; padding:1px 7px; font-size:.7rem; font-weight:700;"><?= count($groupMembers) ?></span>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isGroup): ?>
        <div id="group-members-panel" style="display:none; position:absolute; top:0; right:0; bottom:0; width:320px; background:var(--surface-color); border-left:1px solid var(--border-color); z-index:10; overflow-y:auto; flex-direction:column;">
            <div style="padding:20px 20px 16px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; background:var(--surface-color); z-index:1;">
                <div>
                    <h3 style="margin:0; font-size:1rem;">Members</h3>
                    <p class="muted" style="margin:2px 0 0; font-size:.78rem;" id="member-panel-count"><?= count($groupMembers) ?> member<?= count($groupMembers) !== 1 ? 's' : '' ?></p>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <?php if (in_array($role, ['Academic Admin', 'Teacher'], true)): ?>
                    <button type="button" class="btn secondary sm" onclick="openGroupAddModal()" title="Add Members" style="padding:6px; border-radius:8px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <?php endif; ?>
                    <button onclick="toggleMembersPanel()" style="background:none; border:none; cursor:pointer; color:var(--text-muted); padding:4px; font-size:1.1rem; line-height:1;">✕</button>
                </div>
            </div>
            <?php if (in_array($role, ['Academic Admin', 'Teacher'], true)): ?>
            <div style="padding:12px 16px; border-bottom:1px solid var(--border-color);">
                <button type="button" class="btn" onclick="openGroupAddModal()" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Add Members
                </button>
            </div>
            <?php endif; ?>
            <div id="group-members-list" style="padding:12px 16px; display:grid; gap:8px;"></div>
        </div>
        <?php endif; ?>

        <!-- Messages -->
        <div class="chat-messages" id="chat-messages">
            <?php if (empty($messages)): ?>
                <div class="chat-empty-state">
                    <p class="muted" style="font-size:.875rem;">No messages yet. Say hello!</p>
                </div>
            <?php else: ?>
                <?php
                $prevDate = null;
                foreach ($messages as $msg):
                    $isMine = $msg['sender_role'] === $role && (int)$msg['sender_id'] === $uid;
                    $msgDate = date('Y-m-d', strtotime($msg['sent_at']));
                    if ($msgDate !== $prevDate):
                        $prevDate = $msgDate;
                ?>
                <div class="chat-date-divider">
                    <span><?= date('F j, Y', strtotime($msg['sent_at'])) ?></span>
                </div>
                <?php endif; ?>
                <div class="chat-bubble-wrap <?= $isMine ? 'mine' : 'theirs' ?>" data-date="<?= esc($msgDate) ?>" data-msg-id="<?= (int)$msg['id'] ?>">
                    <?php if ($isGroup && !$isMine): ?>
                        <div class="chat-sender-name" style="font-size:11px; color:var(--herald-red); margin-bottom:4px; font-weight:600;">
                            <?= esc(resolve_user_display_name($pdo, $msg['sender_role'], $msg['sender_id'])) ?>
                        </div>
                    <?php endif; ?>
                    <div class="chat-bubble">
                        <?= nl2br(esc($msg['body'])) ?>
                        <span class="chat-time"><?= date('g:i A', strtotime($msg['sent_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Input area -->
        <!-- Input area — all roles can reply in an existing conversation -->
        <div class="chat-input-bar">
            <div class="chat-input-form" id="chat-form">
                <textarea
                    id="chat-input"
                    class="input chat-textarea"
                    placeholder="Type a message…"
                    rows="1"
                    onkeydown="handleChatKey(event)"></textarea>
                <button type="button" class="btn chat-send-btn" aria-label="Send" onclick="sendMessage()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($isGroup && in_array($role, ['Academic Admin', 'Teacher'], true)): ?>
<div id="group-add-members-modal" class="modal-backdrop" onclick="if(event.target===this)closeGroupAddModal()">
    <div class="modal" style="max-width:520px; text-align:left; padding:0; overflow:hidden;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px 0 24px;">
            <h2 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-main);">Add Members</h2>
            <button class="modal-close" onclick="closeGroupAddModal()" style="position:static; background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:1.2rem; padding:4px;">✕</button>
        </div>
        <div style="padding:20px 24px 24px;">
            <form method="POST" action="<?= APP_BASE_URL ?>/group_chat_add.php">
                <input type="hidden" name="group_id" value="<?= $gid ?>">
                <div class="form-group" style="margin-bottom:14px; display:grid; gap:10px;">
                    <div>
                        <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">Add by</label>
                        <select id="group_add_mode" class="input" onchange="buildGroupAddParticipants()">
                            <option value="student">Student</option>
                            <option value="course">Course</option>
                        </select>
                    </div>
                    <div id="group_add_course_container" style="display:none;">
                        <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">Course</label>
                        <select id="group_add_course_filter" class="input" onchange="buildGroupAddParticipants()">
                            <option value="">— all courses —</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?= (int)$course['id'] ?>"><?= esc($course['course_code'] . ' — ' . $course['course_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">Members</label>
                    <div id="group-add-members-list" style="max-height:260px; overflow-y:auto; border:1px solid var(--border-color); border-radius:10px; padding:6px; background:var(--bg-color);"></div>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn secondary" onclick="closeGroupAddModal()">Cancel</button>
                    <button type="submit" class="btn">Add Members</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const BASE     = '<?= APP_BASE_URL ?>';
const CID      = <?= (int)$cid ?>;
const GID      = <?= (int)$gid ?>;
const IS_GROUP = <?= $isGroup ? 'true' : 'false' ?>;
const MY_ROLE  = '<?= esc($role) ?>';
const MY_ID    = <?= $uid ?>;

<?php if ($isGroup): ?>
const groupMembers = <?= json_encode(array_values($groupMembers ?? [])) ?>;
<?php endif; ?>
<?php if ($isGroup && in_array($role, ['Academic Admin', 'Teacher'], true)): ?>
const groupAddTeacherList = <?= json_encode(array_values(array_map(fn($t) => ['id' => $t['id'], 'name' => trim($t['first_name'].' '.$t['last_name'])], $teachers))) ?>;
const groupAddStudentList = <?= json_encode(array_values($students)) ?>;
const groupAddCourses = <?= json_encode(array_values($courses)) ?>;
const groupAddExistingMembers = <?= json_encode(array_values($existingGroupMembers)) ?>;
const groupAddExistingSet = new Set(groupAddExistingMembers);
<?php endif; ?>

// Track highest message id we've seen
let lastId = <?= !empty($messages) ? (int)end($messages)['id'] : 0 ?>;

const msgContainer = document.getElementById('chat-messages');

function scrollToBottom(force = false) {
    const distFromBottom = msgContainer.scrollHeight - msgContainer.scrollTop - msgContainer.clientHeight;
    if (force || distFromBottom < 120) {
        msgContainer.scrollTop = msgContainer.scrollHeight;
    }
}

<?php if ($isGroup): ?>
let membersPanelOpen = false;
function toggleMembersPanel() {
    membersPanelOpen = !membersPanelOpen;
    const panel = document.getElementById('group-members-panel');
    const btn = document.getElementById('members-toggle-btn');
    if (membersPanelOpen) {
        panel.style.display = 'flex';
        panel.style.flexDirection = 'column';
        btn.style.background = 'var(--surface-hover)';
        buildGroupMembers();
    } else {
        panel.style.display = 'none';
        btn.style.background = '';
    }
}

// Keep switchGroupTab as alias for backward compatibility
function switchGroupTab(tab) {
    if (tab === 'members' && !membersPanelOpen) toggleMembersPanel();
    else if (tab === 'messages' && membersPanelOpen) toggleMembersPanel();
}

<?php if (in_array($role, ['Academic Admin', 'Teacher'], true)): ?>
function openGroupAddModal() {
    document.getElementById('group-add-members-modal').classList.add('open');
    const mode = document.getElementById('group_add_mode');
    if (mode) mode.value = 'student';
    const courseContainer = document.getElementById('group_add_course_container');
    if (courseContainer) courseContainer.style.display = 'none';
    buildGroupAddParticipants();
}

function closeGroupAddModal() {
    document.getElementById('group-add-members-modal').classList.remove('open');
}

function createSectionHeader(title) {
    const header = document.createElement('div');
    header.style.cssText = 'font-size:11px; font-weight:700; color:var(--text-faint); text-transform:uppercase; letter-spacing:.2em; margin:10px 0 6px;';
    header.textContent = title;
    return header;
}

function buildGroupAddParticipants() {
    const container = document.getElementById('group-add-members-list');
    if (!container) return;
    container.innerHTML = '';
    const mode = document.getElementById('group_add_mode')?.value || 'student';
    const selectedCourseId = mode === 'course' ? Number(document.getElementById('group_add_course_filter')?.value || 0) || null : null;
    const courseContainer = document.getElementById('group_add_course_container');
    if (courseContainer) courseContainer.style.display = mode === 'course' ? 'block' : 'none';

    if (groupAddTeacherList.length) {
        container.appendChild(createSectionHeader('Teachers'));
        groupAddTeacherList.forEach(u => {
            const key = `Teacher:${u.id}`;
            if (groupAddExistingSet.has(key)) return;
            // Support both normalized {name} and raw {first_name, last_name}
            const displayName = u.name || (u.first_name + ' ' + u.last_name);
            const label = document.createElement('label');
            label.style.cssText = 'display:flex; align-items:center; gap:10px; padding:8px 10px; cursor:pointer; border-radius:8px; transition:background .15s;';
            label.onmouseenter = () => label.style.background = 'var(--surface-hover)';
            label.onmouseleave = () => label.style.background = '';
            label.innerHTML = `
                <input type="checkbox" name="participants[]" value="Teacher:${u.id}" style="accent-color:var(--herald-red); width:16px; height:16px;">
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:600; font-size:13px; color:var(--text-main);">${displayName}</div>
                    <div style="font-size:11px; color:var(--text-faint); text-transform:uppercase; letter-spacing:.3px;">Teacher</div>
                </div>
            `;
            container.appendChild(label);
        });
    }

    const filteredStudents = groupAddStudentList.filter(u => {
        if (mode === 'course') {
            if (!selectedCourseId) return true;
            return u.courseIds && u.courseIds.includes(selectedCourseId);
        }
        return true;
    });

    container.appendChild(createSectionHeader('Students'));
    const nonMembers = filteredStudents.filter(u => !groupAddExistingSet.has(`Student:${u.id}`));
    if (nonMembers.length === 0) {
        const empty = document.createElement('div');
        empty.style.cssText = 'padding:10px 12px; color:var(--text-muted); font-size:.9rem;';
        empty.textContent = mode === 'course' && selectedCourseId ? 'No students to add from this course.' : 'All eligible students are already members.';
        container.appendChild(empty);
        return;
    }

    nonMembers.forEach(u => {
        const label = document.createElement('label');
        label.style.cssText = 'display:flex; align-items:center; gap:10px; padding:8px 10px; cursor:pointer; border-radius:8px; transition:background .15s;';
        label.onmouseenter = () => label.style.background = 'var(--surface-hover)';
        label.onmouseleave = () => label.style.background = '';
        const coursesLabel = u.courseNames && u.courseNames.length ? u.courseNames.join(', ') : 'No course assigned';
        label.innerHTML = `
            <input type="checkbox" name="participants[]" value="Student:${u.id}" style="accent-color:var(--herald-red); width:16px; height:16px;">
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600; font-size:13px; color:var(--text-main);">${u.name}</div>
                <div style="font-size:11px; color:var(--text-faint); line-height:1.4;">${coursesLabel}</div>
            </div>
        `;
        container.appendChild(label);
    });
}
<?php endif; ?>

function buildGroupMembers() {
    const list = document.getElementById('group-members-list');
    if (!list) return;
    list.innerHTML = '';
    if (!groupMembers.length) {
        const empty = document.createElement('div');
        empty.style.cssText = 'padding:20px; color:var(--text-muted); background:var(--bg-color); border:1px solid var(--border-color); border-radius:12px; text-align:center;';
        empty.textContent = 'No members yet.';
        list.appendChild(empty);
        return;
    }

    groupMembers.forEach(member => {
        const isMe = member.role === MY_ROLE && member.id === MY_ID;
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--bg-color); border:1px solid var(--border-color); border-radius:10px;';

        // Avatar
        const avatar = document.createElement('div');
        avatar.style.cssText = 'flex-shrink:0; width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg, var(--herald-green-dark), var(--herald-green)); color:#1C2B22; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.85rem;';
        avatar.textContent = (member.name || '?')[0].toUpperCase();
        row.appendChild(avatar);

        const info = document.createElement('div');
        info.style.cssText = 'flex:1; min-width:0;';
        const name = document.createElement('div');
        name.style.cssText = 'font-weight:600; color:var(--text-main); font-size:.88rem; display:flex; align-items:center; gap:6px;';
        name.textContent = member.name;
        if (isMe) {
            const youTag = document.createElement('span');
            youTag.style.cssText = 'font-size:.65rem; background:var(--herald-green); color:#1C2B22; border-radius:999px; padding:1px 7px; font-weight:700;';
            youTag.textContent = 'You';
            name.appendChild(youTag);
        }
        const roleLabel = document.createElement('div');
        roleLabel.style.cssText = 'font-size:.75rem; color:var(--text-faint); text-transform:uppercase; letter-spacing:.3px;';
        roleLabel.textContent = member.role;
        info.appendChild(name);
        info.appendChild(roleLabel);
        row.appendChild(info);

        <?php if (in_array($role, ['Academic Admin', 'Teacher'], true)): ?>
        if (!isMe) {
            const action = document.createElement('form');
            action.method = 'POST';
            action.action = `${BASE}/group_chat_remove.php`;
            action.style.cssText = 'margin:0; flex-shrink:0;';
            action.innerHTML = `
                <input type="hidden" name="group_id" value="${GID}">
                <input type="hidden" name="member" value="${member.role}:${member.id}">
                <button type="submit" title="Remove member" style="background:none; border:1px solid var(--border-color); cursor:pointer; color:var(--text-muted); padding:4px 10px; border-radius:7px; font-size:.78rem; transition:all .15s;"
                    onmouseenter="this.style.background='var(--herald-red)';this.style.color='#fff';this.style.border='1px solid var(--herald-red)'"
                    onmouseleave="this.style.background='none';this.style.color='var(--text-muted)';this.style.border='1px solid var(--border-color)'"
                >Remove</button>
            `;
            row.appendChild(action);
        }
        <?php endif; ?>

        list.appendChild(row);
    });
}
<?php endif; ?>

// ── Render a new bubble ───────────────────────────────────────────────────────
function getDateKey(timestamp) {
    const date = timestamp ? new Date(timestamp) : new Date();
    if (Number.isNaN(date.valueOf())) return '';
    return date.toISOString().slice(0, 10);
}

function createDateDivider(dateKey) {
    const divider = document.createElement('div');
    divider.className = 'chat-date-divider';
    const label = document.createElement('span');
    label.textContent = new Date(dateKey).toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
    divider.appendChild(label);
    return divider;
}

function renderBubble(msg) {
    const wrap = document.createElement('div');
    wrap.className = 'chat-bubble-wrap ' + (msg.is_mine ? 'mine' : 'theirs');
    wrap.dataset.msgId = msg.id;
    if (msg.date_key) wrap.dataset.date = msg.date_key;
    else if (msg.sent_at) wrap.dataset.date = getDateKey(msg.sent_at);

    if (IS_GROUP && !msg.is_mine) {
        const name = document.createElement('div');
        name.className = 'chat-sender-name';
        name.style.cssText = 'font-size:11px; color:var(--herald-red); margin-bottom:4px; font-weight:600;';
        name.textContent = msg.sender_name || 'User';
        wrap.appendChild(name);
    }

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';

    const bodyNode = document.createTextNode(msg.body);
    bubble.appendChild(bodyNode);

    const time = document.createElement('span');
    time.className = 'chat-time';
    time.textContent = msg.time_fmt;
    bubble.appendChild(time);

    wrap.appendChild(bubble);
    return wrap;
}

function findExistingOptimisticBubble(msg) {
    const optimisticBubbles = msgContainer.querySelectorAll('.chat-bubble-wrap[data-optimistic="1"]');
    for (const wrap of optimisticBubbles) {
        const bubble = wrap.querySelector('.chat-bubble');
        const time = wrap.querySelector('.chat-time');
        if (!bubble || !time) continue;
        const bodyNode = bubble.childNodes[0];
        const bubbleText = bodyNode ? bodyNode.textContent.trim() : '';
        if (bubbleText === msg.body.trim() && time.textContent === msg.time_fmt) {
            return wrap;
        }
    }
    return null;
}

function appendMessage(msg) {
    const dateKey = msg.date_key || getDateKey(msg.sent_at);
    const lastWrap = msgContainer.querySelector('.chat-bubble-wrap:last-child');
    const lastDate = lastWrap ? lastWrap.dataset.date : null;
    if (!lastWrap || lastDate !== dateKey) {
        msgContainer.appendChild(createDateDivider(dateKey));
    }

    const existing = msgContainer.querySelector(`[data-msg-id="${msg.id}"]`);
    if (existing) return;

    const optimisticMatch = msg.is_mine ? findExistingOptimisticBubble(msg) : null;
    if (optimisticMatch) {
        optimisticMatch.dataset.msgId = msg.id;
        optimisticMatch.removeAttribute('data-optimistic');
        optimisticMatch.dataset.date = dateKey;
        const timeElem = optimisticMatch.querySelector('.chat-time');
        if (timeElem) timeElem.textContent = msg.time_fmt;
        lastId = Math.max(lastId, msg.id);
        return;
    }

    const bubble = renderBubble(msg);
    msgContainer.appendChild(bubble);
    lastId = Math.max(lastId, msg.id);
}

// ── Poll for new messages every 3 seconds ────────────────────────────────────
async function pollMessages() {
    const threadParam = IS_GROUP ? `group_id=${GID}` : `conversation_id=${CID}`;
    try {
        const res = await fetch(`${BASE}/chat_poll.php?${threadParam}&after_id=${lastId}`);
        if (!res.ok) return;
        const data = await res.json();
        if (data.messages && data.messages.length > 0) {
            const empty = msgContainer.querySelector('.chat-empty-state');
            if (empty) empty.remove();

            data.messages.forEach(msg => appendMessage(msg));
            scrollToBottom();
        }
    } catch (e) { /* network error — silently retry */ }
}

setInterval(pollMessages, 3000);

// ── Send message via AJAX ─────────────────────────────────────────────────────
async function sendMessage() {
    const ta   = document.getElementById('chat-input');
    const body = ta.value.trim();
    if (!body) return;

    const timeFmt = new Date().toLocaleTimeString([], {hour:'numeric', minute:'2-digit'});
    const optimistic = renderBubble({
        id: `temp-${Date.now()}`,
        is_mine: true,
        body: body,
        time_fmt: timeFmt,
        date_key: getDateKey()
    });
    optimistic.dataset.optimistic = '1';

    const empty = msgContainer.querySelector('.chat-empty-state');
    if (empty) empty.remove();
    msgContainer.appendChild(optimistic);
    scrollToBottom(true);

    ta.value = '';
    ta.style.height = 'auto';
    ta.focus();

    try {
        const fd = new FormData();
        fd.append('body', body);
        fd.append('ajax', '1');
        const threadParam = IS_GROUP ? `group_id=${GID}` : `id=${CID}`;
        const res = await fetch(`${BASE}/chat_view.php?${threadParam}`, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.id) {
            optimistic.dataset.msgId = data.id;
            optimistic.removeAttribute('data-optimistic');
            lastId = Math.max(lastId, data.id);
        }
    } catch (e) {
        optimistic.style.opacity = '0.5';
        optimistic.title = 'Failed to send — please try again';
    }
}

function handleChatKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// Auto-resize textarea
const ta = document.getElementById('chat-input');
if (ta) {
    ta.addEventListener('input', () => {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
    });
}

// Initial scroll
scrollToBottom(true);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
