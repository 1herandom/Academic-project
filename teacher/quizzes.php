<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user = current_user();
$pdo = db();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM quizzes q WHERE q.teacher_user_id = ?
");
$countStmt->execute([$user['id']]);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

$quizzes = $pdo->prepare("
    SELECT q.*, c.course_code, c.course_title, 
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND completed_at IS NOT NULL) as submission_count
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.teacher_user_id = ?
    ORDER BY q.created_at DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
");
$quizzes->execute([$user['id']]);
$quizzesList = $quizzes->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Quizzes</h1>
        <p class="muted page-subtitle">Manage quizzes for your courses.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/teacher/create_quiz.php" class="btn">Create Quiz</a>
</div>

<div class="panel">
    <?php if (empty($quizzesList)): ?>
        <div class="text-center" style="padding: 3rem 0;">
            <svg class="muted" style="width: 48px; height: 48px; margin-bottom: 1rem;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <h3 style="margin-bottom: 0.5rem;">No Quizzes Found</h3>
            <p class="muted">You haven't created any quizzes yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Quiz Title</th>
                        <th>Course</th>
                        <th>Duration</th>
                        <th>Questions</th>
                        <th>Submissions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzesList as $q): ?>
                        <tr>
                            <td><strong><?= esc($q['title']) ?></strong></td>
                            <td><?= esc($q['course_code']) ?></td>
                            <td><?= (int)$q['duration_minutes'] ?> mins</td>
                            <td><?= (int)$q['question_count'] ?></td>
                            <td>
                                <?php if ((int)$q['submission_count'] > 0): ?>
                                    <span class="pill green"><?= (int)$q['submission_count'] ?></span>
                                <?php else: ?>
                                    <span class="pill gray">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a href="<?= APP_BASE_URL ?>/teacher/quiz_results.php?id=<?= $q['id'] ?>" class="btn sm green">View Results</a>
                                <a href="<?= APP_BASE_URL ?>/teacher/quiz_questions.php?id=<?= $q['id'] ?>" class="btn sm secondary">Manage Questions</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
