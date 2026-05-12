<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

$user = current_user();

// Fetch sent notices
$notices = db()->prepare("SELECT * FROM notices WHERE sender_role = 'Admin' AND sender_id = ? ORDER BY created_at DESC");
$notices->execute([$user['id']]);
$notices = $notices->fetchAll();

?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Notice Board</h1>
        <p class="muted page-subtitle">View your sent announcements to courses, teachers, or students.</p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/admin/add_notice.php" class="btn">Add Notice</a>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">Sent Notices</h3>
    </div>
    <?php if (empty($notices)): ?>
        <p class="muted mt-4">You have not sent any notices yet.</p>
    <?php else: ?>
        <div class="table-wrap mt-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Target</th>
                        <th>Title</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notices as $n): ?>
                        <tr>
                            <td><?= esc(date('Y-m-d', strtotime($n['created_at']))) ?></td>
                            <td>
                                <?php 
                                    if ($n['target_type'] === 'all') echo 'Everyone';
                                    elseif ($n['target_type'] === 'all_teachers') echo 'All Teachers';
                                    elseif ($n['target_type'] === 'all_students') echo 'All Students';
                                    elseif ($n['target_type'] === 'course') echo 'Course ID: ' . $n['target_id'];
                                    elseif ($n['target_type'] === 'teacher') echo 'Teacher ID: ' . $n['target_id'];
                                    elseif ($n['target_type'] === 'student') echo 'Student ID: ' . $n['target_id'];
                                ?>
                            </td>
                            <td><strong><?= esc($n['title']) ?></strong></td>
                            <td>
                                <a href="<?= APP_BASE_URL ?>/admin/edit_notice.php?id=<?= $n['id'] ?>" class="btn sm secondary">Edit</a>
                                <form method="post" action="<?= APP_BASE_URL ?>/admin/delete_notice.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn sm" style="background:var(--herald-red); color:white; border:none;" onclick="return confirm('Are you sure you want to delete this notice?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
