<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user = current_user();
$pdo = db();

// Fetch courses taught by this teacher
$coursesStmt = $pdo->prepare("SELECT id FROM courses WHERE teacher_user_id = ?");
$coursesStmt->execute([$user['id']]);
$courseIds = array_column($coursesStmt->fetchAll(), 'id');

// Fetch notices visible to this teacher
$inClause = empty($courseIds) ? "0" : implode(',', $courseIds);
$query = "
    SELECT * FROM notices 
    WHERE target_type IN ('all', 'all_teachers')
       OR (target_type = 'teacher' AND target_id = ?)
       OR (target_type = 'course' AND target_id IN ($inClause))
       OR (sender_role = 'Teacher' AND sender_id = ?)
    ORDER BY created_at DESC
";
$noticesStmt = $pdo->prepare($query);
$noticesStmt->execute([$user['id'], $user['id']]);
$notices = $noticesStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Notice Board</h1>
        <p class="muted page-subtitle">View notices and announcements.</p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/teacher/add_notice.php" class="btn">Add Notice</a>
    </div>
</div>

<div class="card">
    <div class="panel-header">
        <h3 class="panel-title">My Notices</h3>
    </div>
    <?php if (empty($notices)): ?>
        <p class="muted mt-4">You have no notices at this time.</p>
    <?php else: ?>
        <div class="flex flex-col mt-4" style="gap:16px;">
            <?php foreach ($notices as $n): ?>
                <div class="panel" style="background:var(--bg-card); border-left:4px solid <?= $n['sender_role'] === 'Admin' ? 'var(--herald-red)' : 'var(--herald-amber)' ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <h4 style="margin:0 0 8px; font-size:16px;"><?= esc($n['title']) ?></h4>
                        <span class="muted" style="font-size:12px;"><?= esc(date('M j, Y g:i A', strtotime($n['created_at']))) ?></span>
                    </div>
                    <p style="margin:0 0 12px; font-size:14px; color:var(--text-color);"><?= nl2br(esc($n['content'])) ?></p>
                    
                    <div style="font-size:12px; color:var(--text-muted); display:flex; gap:16px; align-items:center; justify-content:space-between; width:100%;">
                        <div style="display:flex; gap:16px;">
                            <span><strong>From:</strong> <?= esc($n['sender_role']) ?></span>
                            <span><strong>To:</strong> 
                                <?php 
                                    if ($n['target_type'] === 'all') echo 'Everyone';
                                    elseif ($n['target_type'] === 'all_teachers') echo 'All Teachers';
                                    elseif ($n['target_type'] === 'course') echo 'Course (ID ' . $n['target_id'] . ')';
                                    elseif ($n['target_type'] === 'teacher') echo 'Me';
                                    elseif ($n['target_type'] === 'student') echo 'Student (ID ' . $n['target_id'] . ')';
                                ?>
                            </span>
                        </div>
                        <?php if ($n['sender_role'] === 'Teacher' && (int)$n['sender_id'] === (int)$user['id']): ?>
                            <div style="display:flex; gap:8px;">
                                <a href="<?= APP_BASE_URL ?>/teacher/edit_notice.php?id=<?= $n['id'] ?>" class="btn sm secondary">Edit</a>
                                <form method="post" action="<?= APP_BASE_URL ?>/teacher/delete_notice.php" style="margin:0;">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn sm" style="background:var(--herald-red); color:white; border:none;" onclick="return confirm('Are you sure you want to delete this notice?');">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
