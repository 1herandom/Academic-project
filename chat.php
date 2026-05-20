<?php
require_once __DIR__ . '/config.php';
require_role(['Academic Admin', 'Teacher', 'Student']);

$user = current_user();
$role = $user['role'];
$uid  = (int)$user['id'];
$pdo  = db();

// ── Fetch all conversations this user is part of ──────────────────────────────
$convos = $pdo->prepare("
    SELECT c.*,
           m.body         AS last_msg,
           m.sent_at      AS last_sent,
           m.sender_role  AS last_sender_role,
           (SELECT COUNT(*) FROM chat_messages cm2
            WHERE cm2.conversation_id = c.id
              AND cm2.is_read = 0
              AND cm2.sender_role != ?
              AND cm2.sender_id   != ?) AS unread_count
    FROM chat_conversations c
    LEFT JOIN chat_messages m ON m.id = (
        SELECT id FROM chat_messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1
    )
    WHERE (c.initiator_role = ? AND c.initiator_id = ?)
       OR (c.participant_role = ? AND c.participant_id = ?)
    ORDER BY COALESCE(m.sent_at, c.created_at) DESC
");
$convos->execute([$role, $uid, $role, $uid, $role, $uid]);
$conversations = $convos->fetchAll();

// ── Resolve display names for each conversation ───────────────────────────────
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

// ── Fetch all group chats this user is part of ────────────────────────────────
$groupsStmt = $pdo->prepare("
    SELECT g.id, g.name, 
           m.body AS last_msg, 
           m.sent_at AS last_sent,
           'group' AS type
    FROM group_chats g
    JOIN group_chat_members gm ON gm.group_id = g.id
    LEFT JOIN chat_messages m ON m.id = (
        SELECT id FROM chat_messages WHERE group_id = g.id ORDER BY sent_at DESC LIMIT 1
    )
    WHERE gm.user_role = ? AND gm.user_id = ?
    ORDER BY COALESCE(m.sent_at, g.created_at) DESC
");
$groupsStmt->execute([$role, $uid]);
$groups = $groupsStmt->fetchAll();

// ── Resolve display names and prepare for unified list ────────────────────────
$unifiedList = [];

// Add 1-to-1 conversations
foreach ($conversations as $c) {
    if ($c['initiator_role'] === $role && (int)$c['initiator_id'] === $uid) {
        $other_role = $c['participant_role'];
        $other_id   = $c['participant_id'];
    } else {
        $other_role = $c['initiator_role'];
        $other_id   = $c['initiator_id'];
    }
    $unifiedList[] = [
        'id'           => $c['id'],
        'type'         => 'direct',
        'display_name' => resolve_user_display_name($pdo, $other_role, (int)$other_id),
        'last_msg'     => $c['last_msg'],
        'last_sent'    => $c['last_sent'],
        'unread_count' => $c['unread_count']
    ];
}

// Add Group chats
foreach ($groups as $g) {
    $gUnreadStmt = $pdo->prepare("
        SELECT COUNT(*) FROM chat_messages cm
        JOIN group_chat_members gm ON gm.group_id = cm.group_id
        WHERE gm.group_id = ? AND gm.user_role = ? AND gm.user_id = ?
          AND cm.sent_at > gm.last_read_at
          AND (cm.sender_role != ? OR cm.sender_id != ?)
    ");
    $gUnreadStmt->execute([$g['id'], $role, $uid, $role, $uid]);
    $gUnreadCount = (int)$gUnreadStmt->fetchColumn();

    $unifiedList[] = [
        'id'           => $g['id'],
        'type'         => 'group',
        'display_name' => $g['name'],
        'last_msg'     => $g['last_msg'],
        'last_sent'    => $g['last_sent'],
        'unread_count' => $gUnreadCount
    ];
}

// Sort unified list by last activity
usort($unifiedList, fn($a, $b) => strcmp($b['last_sent'] ?? '0', $a['last_sent'] ?? '0'));

// ── Build user lists for "New Conversation" modal (Admin & Teacher only) ──────
$teachers = [];
$students  = [];
$admins    = [];
$courses   = [];

if ($role === 'Academic Admin') {
    $admins = $pdo->prepare("SELECT id, first_name, last_name FROM admins WHERE id != ? ORDER BY first_name");
    $admins->execute([$uid]);
    $admins = $admins->fetchAll();
    $teachers = $pdo->query("SELECT id, first_name, last_name FROM teachers WHERE status='active' ORDER BY first_name")->fetchAll();
    $students = $pdo->query(
        "SELECT s.id, s.first_name, s.last_name, c.id AS course_id, c.course_code, c.course_title
         FROM students s
         LEFT JOIN enrollments e ON e.student_user_id = s.id
         LEFT JOIN courses c ON c.id = e.course_id
         WHERE s.status = 'active'
         ORDER BY s.first_name"
    )->fetchAll();
    $courses = $pdo->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code")->fetchAll();
} elseif ($role === 'Teacher') {
    // Teachers can only chat with students enrolled in their courses + other teachers
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

    $teachers = $pdo->prepare("SELECT id, first_name, last_name FROM teachers WHERE status='active' AND id != ? ORDER BY first_name");
    $teachers->execute([$uid]);
    $teachers = $teachers->fetchAll();

    $courses = $pdo->prepare("SELECT id, course_code, course_title FROM courses WHERE teacher_user_id = ? ORDER BY course_code");
    $courses->execute([$uid]);
    $courses = $courses->fetchAll();
}

// Normalize students with course metadata for the JS participant builder
$studentMap = [];
foreach ($students as $row) {
    $id = (int)$row['id'];
    if (!isset($studentMap[$id])) {
        $studentMap[$id] = [
            'id' => $id,
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'role' => 'Student',
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

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/chat.css">

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Messages</h1>
        <p class="muted">
            <?php if ($role === 'Student'): ?>
                View and reply to messages from teachers or admin. You cannot start new conversations.
            <?php else: ?>
                Start or continue conversations with staff and students.
            <?php endif; ?>
        </p>
    </div>
    <?php if ($role !== 'Student'): ?>
    <button type="button" class="btn" onclick="openChatModal()">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px;vertical-align:-2px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        New Conversation
    </button>
    <?php endif; ?>
</div>

<div class="chat-layout">
    <aside class="chat-sidebar">
        <?php if (empty($unifiedList)): ?>
            <div class="chat-empty-state">
                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="opacity:.4;margin-bottom:12px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.862 9.862 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="muted" style="font-size:.875rem;text-align:center;">No conversations yet<?= $role !== 'Student' ? '.<br>Start one above.' : '.' ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($unifiedList as $c): 
                $isGrp = $c['type'] === 'group';
                $link = $isGrp ? "chat_view.php?group_id=".$c['id'] : "chat_view.php?id=".$c['id'];
            ?>
            <a class="chat-thread-item" href="<?= APP_BASE_URL ?>/<?= $link ?>">
                <div class="chat-avatar" style="<?= $isGrp ? 'background:linear-gradient(135deg, var(--herald-red), #a855f7); color:white;' : '' ?>">
                    <?= strtoupper(substr($c['display_name'], 0, 1)) ?>
                </div>
                <div class="chat-thread-meta">
                    <div class="chat-thread-top">
                        <span class="chat-thread-name"><?= esc($c['display_name']) ?></span>
                        <span class="chat-thread-time"><?= $c['last_sent'] ? date('M j', strtotime($c['last_sent'])) : '' ?></span>
                    </div>
                    <div class="chat-thread-bottom">
                        <span class="chat-thread-preview"><?= esc(mb_strimwidth($c['last_msg'] ?? 'No messages yet', 0, 50, '…')) ?></span>
                        <?php if ($c['unread_count'] > 0): ?>
                        <span class="chat-badge"><?= (int)$c['unread_count'] ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($isGrp): ?>
                        <span class="chat-role-tag">Group</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>

    <div class="chat-main chat-main--empty">
        <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="opacity:.25;margin-bottom:16px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.862 9.862 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <p class="muted">Select a conversation to read messages</p>
    </div>
</div>

<?php if ($role !== 'Student'): ?>
<!-- ── Unified Compose Modal ── -->
<div id="compose-modal" class="modal-backdrop" style="background:transparent; backdrop-filter:none; -webkit-backdrop-filter:none;" onclick="if(event.target===this)closeChatModal()">
    <div class="modal" style="max-width:500px; text-align:left; padding:0; overflow:hidden;">
        <!-- Modal Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px 0 24px;">
            <h2 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-main);">New Conversation</h2>
            <button class="modal-close" onclick="closeChatModal()" style="position:static; background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:1.2rem; padding:4px;">✕</button>
        </div>

        <!-- Tab Switcher -->
        <div id="compose-tabs" style="display:flex; margin:16px 24px 0; border-radius:10px; background:var(--bg-color); padding:3px; gap:2px;">
            <button type="button" class="compose-tab active" data-tab="direct" onclick="switchComposeTab('direct')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Direct
            </button>
            <button type="button" class="compose-tab" data-tab="group" onclick="switchComposeTab('group')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Group
            </button>
        </div>

        <!-- Direct Message Form -->
        <div id="tab-direct" class="compose-panel" style="padding:20px 24px 24px;">
            <form method="POST" action="<?= APP_BASE_URL ?>/chat_start.php">
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">To</label>
                    <select name="participant_role" id="participant_role" class="input" onchange="filterRecipients()" required style="margin-bottom:8px;">
                        <option value="">— select role —</option>
                        <?php if ($role === 'Academic Admin'): ?>
                        <option value="Academic Admin">Admin</option>
                        <?php endif; ?>
                        <option value="Teacher">Teacher</option>
                        <option value="Student">Student</option>
                    </select>
                    <div id="recipient-group" style="display:none;">
                        <select name="participant_id" id="participant_id" class="input" required>
                            <option value="">— select person —</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">Message</label>
                    <textarea name="first_message" class="input" rows="3" placeholder="Write your message…" required style="resize:vertical;"></textarea>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn secondary" onclick="closeChatModal()">Cancel</button>
                    <button type="submit" class="btn">Send Message</button>
                </div>
            </form>
        </div>

        <!-- Group Chat Form -->
        <div id="tab-group" class="compose-panel" style="padding:20px 24px 24px; display:none;">
            <form method="POST" action="<?= APP_BASE_URL ?>/group_chat_create.php">
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">Group Name</label>
                    <input type="text" name="group_name" class="input" placeholder="e.g. Science Project Team" required>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">Course</label>
                    <select id="group_course_filter" class="input" onchange="buildGroupParticipants()">
                        <option value="">— all courses —</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?= (int)$course['id'] ?>"><?= esc($course['course_code'] . ' — ' . $course['course_title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">Participants</label>
                    <div id="group-participants-list" style="max-height:220px; overflow-y:auto; border:1px solid var(--border-color); border-radius:10px; padding:6px; background:var(--bg-color);">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="label" style="font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:6px;">Opening Message</label>
                    <textarea name="first_message" class="input" rows="2" placeholder="Welcome to the group!" required style="resize:vertical;"></textarea>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn secondary" onclick="closeChatModal()">Cancel</button>
                    <button type="submit" class="btn">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.compose-tab {
    flex: 1;
    padding: 8px 12px;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: .85rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all .2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.compose-tab:hover { background: var(--surface-hover); }
.compose-tab.active {
    background: var(--surface-color);
    color: var(--text-main);
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
}
</style>

<script>
const courseList = <?= json_encode($courses) ?>;
const adminList  = <?= json_encode(array_map(fn($a) => ['id' => $a['id'], 'name' => trim($a['first_name'].' '.$a['last_name'])], $admins ?? [])) ?>;
const teacherList = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => trim($t['first_name'].' '.$t['last_name'])], $teachers)) ?>;
const studentList = <?= json_encode($students) ?>;

function openChatModal() {
    const modal = document.getElementById('compose-modal');
    modal.classList.add('open');
    switchComposeTab('direct');
    buildGroupParticipants();
}

function closeChatModal() {
    document.getElementById('compose-modal').classList.remove('open');
}

function switchComposeTab(tab) {
    document.querySelectorAll('.compose-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    document.getElementById('tab-direct').style.display = tab === 'direct' ? 'block' : 'none';
    document.getElementById('tab-group').style.display  = tab === 'group'  ? 'block' : 'none';
}

function filterRecipients() {
    const role = document.getElementById('participant_role').value;
    const sel  = document.getElementById('participant_id');
    const grp  = document.getElementById('recipient-group');
    sel.innerHTML = '<option value="">— select person —</option>';
    let list = [];
    if (role === 'Academic Admin') list = adminList;
    else if (role === 'Teacher') list = teacherList;
    else if (role === 'Student') list = studentList.map(u => ({ id: u.id, name: u.name }));
    list.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = u.name;
        sel.appendChild(opt);
    });
    grp.style.display = role ? 'block' : 'none';
}

function createSectionHeader(title) {
    const header = document.createElement('div');
    header.style.cssText = 'font-size:11px; font-weight:700; color:var(--text-faint); text-transform:uppercase; letter-spacing:.2em; margin:10px 0 6px;';
    header.textContent = title;
    return header;
}

function buildGroupParticipants() {
    const list = document.getElementById('group-participants-list');
    list.innerHTML = '';
    const selectedCourseId = document.getElementById('group_course_filter')?.value;
    const courseFilter = selectedCourseId ? Number(selectedCourseId) : null;

    if (teacherList.length) {
        list.appendChild(createSectionHeader('Teachers'));
        teacherList.forEach(u => {
            const label = document.createElement('label');
            label.style.cssText = 'display:flex; align-items:center; gap:10px; padding:8px 10px; cursor:pointer; border-radius:8px; transition:background .15s;';
            label.onmouseenter = () => label.style.background = 'var(--surface-hover)';
            label.onmouseleave = () => label.style.background = '';
            label.innerHTML = `
                <input type="checkbox" name="participants[]" value="Teacher:${u.id}" style="accent-color:var(--herald-red); width:16px; height:16px;">
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:600; font-size:13px; color:var(--text-main);">${u.name}</div>
                    <div style="font-size:11px; color:var(--text-faint); text-transform:uppercase; letter-spacing:.3px;">Teacher</div>
                </div>
            `;
            list.appendChild(label);
        });
    }

    const filteredStudents = studentList.filter(u => {
        if (!courseFilter) return true;
        return u.courseIds && u.courseIds.includes(courseFilter);
    });

    list.appendChild(createSectionHeader('Students'));
    if (filteredStudents.length === 0) {
        const empty = document.createElement('div');
        empty.style.cssText = 'padding:10px 12px; color:var(--text-muted); font-size:.9rem;';
        empty.textContent = selectedCourseId ? 'No students are enrolled in this course.' : 'No students available.';
        list.appendChild(empty);
        return;
    }

    filteredStudents.forEach(u => {
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
        list.appendChild(label);
    });
}

// ── Auto-open compose modal from URL params (e.g. from Course Management) ──
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('compose') === 'group') {
        openChatModal();
        switchComposeTab('group');
        const courseId = params.get('course_id');
        if (courseId) {
            const sel = document.getElementById('group_course_filter');
            if (sel) { sel.value = courseId; buildGroupParticipants(); }
        }
        // Clean URL without reload
        const clean = window.location.pathname;
        history.replaceState(null, '', clean);
    }
})();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
