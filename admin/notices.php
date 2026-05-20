<?php
/*
|--------------------------------------------------------------------------
| Feature By | Rijan Adhikari: Notice board feature
  Feature By | Bipin Guragain: CSRF tokens and other security features
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

$user = current_user();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;

$countStmt = db()->prepare("SELECT COUNT(*) FROM notices WHERE sender_role = 'Admin' AND sender_id = ?");
$countStmt->execute([$user['id']]);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = ceil($totalRecords / $limit);
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
$offset = ($page - 1) * $limit;

$noticesStmt = db()->prepare("SELECT * FROM notices WHERE sender_role = 'Admin' AND sender_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
$noticesStmt->execute([$user['id']]);
$notices = $noticesStmt->fetchAll();

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
                                    elseif ($n['target_type'] === 'course')  echo 'Course ID: '  . esc((string)$n['target_id']);
                                    elseif ($n['target_type'] === 'teacher') echo 'Teacher ID: ' . esc((string)$n['target_id']);
                                    elseif ($n['target_type'] === 'student') echo 'Student ID: ' . esc((string)$n['target_id']);
                                ?>
                            </td>
                            <td><strong><?= esc($n['title']) ?></strong></td>
                            <td>
                                <a href="<?= APP_BASE_URL ?>/admin/edit_notice.php?id=<?= $n['id'] ?>" class="btn sm secondary">Edit</a>
                                <form method="post" action="<?= APP_BASE_URL ?>/admin/delete_notice.php" style="display:inline;">
                                    <!-- AP-44 CSRF | Feature Maker: Bipin Guragain -->
                                    <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">
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

<?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="pagination" style="margin-top: 20px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php
                $qParams = $_GET;
                $qParams['page'] = $i;
                $qString = http_build_query($qParams);
            ?>
            <a href="?<?= $qString ?>" class="btn sm <?= $i === $page ? 'amber' : 'secondary' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
