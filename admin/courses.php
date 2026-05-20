<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

/*
|--------------------------------------------------------------------------
| Feature 2 | Bipin Guragain: Admin course management for role dashboards
|--------------------------------------------------------------------------
*/

$pdo = db();

$searchQuery = trim($_GET['search'] ?? '');
$teacherFilter = (int)($_GET['filter_teacher'] ?? 0);

$sqlBase = " FROM courses c 
        LEFT JOIN teachers u ON c.teacher_user_id = u.id 
        WHERE 1=1";
$where = "";
$params = [];

if ($teacherFilter > 0) {
    $where .= " AND c.teacher_user_id = ?";
    $params[] = $teacherFilter;
} elseif ($teacherFilter === -1) {
    $where .= " AND c.teacher_user_id IS NULL";
}

if ($searchQuery !== '') {
    $where .= " AND (c.course_code LIKE ? OR c.course_title LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
}

$countStmt = $pdo->prepare("SELECT COUNT(*)" . $sqlBase . $where);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$totalPages = ceil($totalRecords / $limit);
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
$offset = ($page - 1) * $limit;

$sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) AS teacher_name" . $sqlBase . $where . " ORDER BY c.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$teachers = $pdo->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'active' ORDER BY first_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Course Management</h1>
        <p class="muted page-subtitle">Manage your institution's course catalog.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/admin/add_course.php" class="btn">Add Course</a>
</div>

<form method="get" class="panel mt-4 gap-4 flex-wrap flex-align-end">
    <label class="flex-1 min-w-200" style="margin-bottom:0;"><span class="small">Search Courses</span>
        <input class="input" type="text" name="search" value="<?= esc($searchQuery) ?>" placeholder="Course Code, Title...">
    </label>
    <label class="w-200" style="margin-bottom:0;"><span class="small">Filter Teacher</span>
        <select class="input" name="filter_teacher">
            <option value="">All Teachers</option>
            <option value="-1" <?= $teacherFilter === -1 ? 'selected' : '' ?>>Unassigned Only</option>
            <?php foreach ($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $teacherFilter === (int)$t['id'] ? 'selected' : '' ?>><?= esc($t['first_name'] . ' ' . $t['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="submit">Filter</button>
    <?php if ($searchQuery !== '' || $teacherFilter !== 0): ?>
        <a href="<?= APP_BASE_URL ?>/admin/courses.php" class="btn secondary">Clear</a>
    <?php endif; ?>
</form>

<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Course Code</th><th>Title</th><th>Teacher</th><th>Created</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><?= esc($c['course_code']) ?></td>
                    <td><?= esc($c['course_title']) ?></td>
                    <td><?php if ($c['teacher_name']): ?><?= esc($c['teacher_name']) ?><?php else: ?><span class="muted">Unassigned</span><?php endif; ?></td>
                    <td><?= esc($c['created_at']) ?></td>
                    <td>
                        <a href="<?= APP_BASE_URL ?>/admin/edit_course.php?id=<?= (int)$c['id'] ?>" class="btn secondary mt-1" style="min-height:33px; padding:6px 12px; font-size:12px;">Edit</a>
                        <a href="<?= APP_BASE_URL ?>/group_chat_create.php?course_id=<?= (int)$c['id'] ?>" class="btn secondary mt-1" style="min-height:33px; padding:6px 12px; font-size:12px;" title="Create group chat for this course">💬 Group Chat</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
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
