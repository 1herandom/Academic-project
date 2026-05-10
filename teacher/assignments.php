<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');

$pdo = db();
$teacherId = current_user()['id'];

$assignments = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_title
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    WHERE a.created_by = ?
    ORDER BY a.deadline_at DESC
");
$assignments->execute([$teacherId]);
$assignments = $assignments->fetchAll();
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Assignments</h1>
        <p class="muted page-subtitle">Manage published assessments.</p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/teacher/add_assignment.php" class="btn">Add Assignment</a>
    </div>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Course</th><th>Title</th><th>Deadline</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><span class="pill muted font-mono"><?= esc($a['course_code']) ?></span></td>
                        <td><?= esc($a['title']) ?></td>
                        <td><?= esc($a['deadline_at']) ?></td>
                        <td>
                            <a class="btn sm secondary" href="<?= APP_BASE_URL ?>/teacher/view_submissions.php?assignment_id=<?= (int)$a['id'] ?>">View Submissions</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
