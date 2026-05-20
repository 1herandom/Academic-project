<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Student');

$user = current_user();
$pdo = db();

// Fetch courses this student is enrolled in
$coursesStmt = $pdo->prepare("
    SELECT c.id 
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_user_id = ?
");
$coursesStmt->execute([$user['id']]);
$courseIds = array_column($coursesStmt->fetchAll(), 'id');

// Fetch notices visible to this student
$inClause = empty($courseIds) ? "0" : implode(',', $courseIds);
$query = "
    SELECT * FROM notices 
    WHERE target_type IN ('all', 'all_students')
       OR (target_type = 'student' AND target_id = ?)
       OR (target_type = 'course' AND target_id IN ($inClause))
    ORDER BY created_at DESC
";
$noticesStmt = $pdo->prepare($query);
$noticesStmt->execute([$user['id']]);
$notices = $noticesStmt->fetchAll();

?>

<div class="content-header">
    <div class="header-content">
        <h2>Notice Board</h2>
        <p class="muted">View important announcements from administrators and your teachers.</p>
    </div>
</div>

<div class="grid grid-cols-1" style="max-width:800px; margin:0 auto; gap:24px;">
    
    <div class="card">
        <h3>My Notices</h3>
        <?php if (empty($notices)): ?>
            <div style="padding:48px; text-align:center; background:var(--bg-card); border-radius:12px; margin-top:16px;">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:48px;height:48px;color:var(--text-faint);margin:0 auto 16px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <h3 style="margin:0 0 8px; color:var(--text-color);">No notices yet</h3>
                <p class="muted" style="margin:0;">You're all caught up. Announcements will appear here.</p>
            </div>
        <?php else: ?>
            <div class="flex flex-col" style="gap:16px; margin-top:16px;">
                <?php foreach ($notices as $n): ?>
                    <div class="panel" style="background:var(--bg-card); border-left:4px solid <?= $n['sender_role'] === 'Admin' ? 'var(--herald-red)' : 'var(--herald-green)' ?>;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <h4 style="margin:0 0 8px; font-size:18px; color:var(--text-color);"><?= esc($n['title']) ?></h4>
                            <span class="muted" style="font-size:13px;"><?= esc(date('M j, Y g:i A', strtotime($n['created_at']))) ?></span>
                        </div>
                        <p style="margin:0 0 16px; font-size:15px; color:var(--text-color); line-height:1.5;"><?= nl2br(esc($n['content'])) ?></p>
                        
                        <div style="font-size:13px; color:var(--text-muted); display:flex; gap:16px; border-top:1px solid var(--border-color); padding-top:12px;">
                            <span><strong>Sender:</strong> <?= esc($n['sender_role']) ?></span>
                            <span><strong>Scope:</strong> 
                                <?php 
                                    if ($n['target_type'] === 'all') echo 'Everyone';
                                    elseif ($n['target_type'] === 'all_students') echo 'All Students';
                                    elseif ($n['target_type'] === 'course') echo 'Course (ID ' . $n['target_id'] . ')';
                                    elseif ($n['target_type'] === 'student') echo 'Direct Message';
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
