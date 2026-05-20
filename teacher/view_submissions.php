<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');

$pdo = db();
$teacherId = current_user()['id'];
$assignmentId = (int)($_GET['assignment_id'] ?? 0);

if (!$assignmentId) {
    flash_set('error', 'Invalid assignment ID.');
    redirect('/teacher/assignments.php');
}

// Verify assignment belongs to this teacher
$stmt = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_title 
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    WHERE a.id = ? AND a.created_by = ?
");
$stmt->execute([$assignmentId, $teacherId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    flash_set('error', 'Assignment not found or permission denied.');
    redirect('/teacher/assignments.php');
}

// Fetch submissions
$subStmt = $pdo->prepare("
    SELECT s.*, st.first_name, st.last_name, st.institutional_id
    FROM submissions s
    JOIN students st ON st.id = s.student_user_id
    WHERE s.assignment_id = ?
    ORDER BY s.updated_at DESC
");
$subStmt->execute([$assignmentId]);
$submissions = $subStmt->fetchAll();
?>

<div class="page-header-layout">
    <div>
        <a href="<?= APP_BASE_URL ?>/teacher/assignments.php" class="muted small" style="display:inline-block;margin-bottom:0.5rem;text-decoration:none;">&larr; Back to Assignments</a>
        <h1 class="page-title">Submissions: <?= esc($assignment['title']) ?></h1>
        <p class="muted page-subtitle"><?= esc($assignment['course_code']) ?> - <?= esc($assignment['course_title']) ?></p>
    </div>
    <div class="stat gold" style="padding: 0.5rem 1rem; border-radius: var(--radius-sm);">
        <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Total Submissions</div>
        <div style="font-size:1.5rem; font-weight:800;"><?= count($submissions) ?></div>
    </div>
</div>

<div class="panel">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Submitted File</th>
                    <th>Submitted At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr>
                        <td colspan="5" class="muted" style="text-align:center; padding:2rem;">No submissions yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td><span class="pill muted" style="font-family:monospace;"><?= esc($sub['institutional_id']) ?></span></td>
                            <td><?= esc($sub['first_name'] . ' ' . $sub['last_name']) ?></td>
                            <td><?= esc($sub['original_filename']) ?></td>
                            <td><?= esc($sub['updated_at']) ?> UTC</td>
                            <td>
                                <a class="btn sm green" href="<?= APP_BASE_URL ?>/storage/uploads/assignments/<?= esc($sub['stored_filename']) ?>" target="_blank" rel="noopener">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    Download
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
