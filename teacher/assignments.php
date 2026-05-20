<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');

$pdo = db();
$teacherId = current_user()['id'];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM assignments a
    JOIN courses c ON c.id = a.course_id
    WHERE a.created_by = ?
");
$countStmt->execute([$teacherId]);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

$assignments = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_title
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    WHERE a.created_by = ?
    ORDER BY a.deadline_at DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
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
    <div style="margin-bottom: 1.5rem; position: relative;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--text-faint);">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" id="assignmentSearch" class="input" placeholder="Search assignments by title or course..." style="padding-left:38px;" onkeyup="filterAssignments()">
    </div>

    <div class="table-wrap">
        <table id="assignmentsTable">
            <thead><tr><th>Course</th><th>Title</th><th>Deadline</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                <tr><td colspan="4" style="text-align:center; padding:32px 0; color:var(--text-muted);">No assignments found.</td></tr>
                <?php else: ?>
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
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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

<script>
function filterAssignments() {
    const q = document.getElementById('assignmentSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#assignmentsTable tbody tr');
    let hasResults = false;

    rows.forEach(row => {
        if (row.cells.length < 3) return;
        const text = row.innerText.toLowerCase();
        if (text.includes(q)) {
            row.style.display = '';
            hasResults = true;
        } else {
            row.style.display = 'none';
        }
    });

    let emptyMsg = document.getElementById('noResultsRow');
    if (!hasResults) {
        if (!emptyMsg) {
            emptyMsg = document.createElement('tr');
            emptyMsg.id = 'noResultsRow';
            emptyMsg.innerHTML = `<td colspan="4" style="text-align:center; padding:32px 0; color:var(--text-muted);">No matching assignments found.</td>`;
            document.querySelector('#assignmentsTable tbody').appendChild(emptyMsg);
        }
        emptyMsg.style.display = '';
    } else if (emptyMsg) {
        emptyMsg.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
