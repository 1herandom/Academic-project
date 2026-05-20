<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user = current_user();
$pdo = db();
$quiz_id = (int)($_GET['id'] ?? 0);

// Verify quiz belongs to this teacher
$quizStmt = $pdo->prepare("
    SELECT q.*, c.course_code, c.course_title,
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as total_questions
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.id = ? AND q.teacher_user_id = ?
");
$quizStmt->execute([$quiz_id, $user['id']]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    flash_set('error', 'Quiz not found or access denied.');
    redirect('/teacher/quizzes.php');
}

// Fetch all attempts for this quiz
$attemptsStmt = $pdo->prepare("
    SELECT qa.*, s.first_name, s.last_name, s.institutional_id
    FROM quiz_attempts qa
    JOIN students s ON s.id = qa.student_user_id
    WHERE qa.quiz_id = ?
    ORDER BY qa.completed_at DESC, qa.started_at DESC
");
$attemptsStmt->execute([$quiz_id]);
$attempts = $attemptsStmt->fetchAll();

// Calculate stats
$completedAttempts = array_filter($attempts, fn($a) => $a['completed_at'] !== null);
$completedCount = count($completedAttempts);
$avgScore = 0;
$highScore = 0;
$lowScore = PHP_INT_MAX;

if ($completedCount > 0) {
    $scores = array_map(fn($a) => (int)$a['score'], $completedAttempts);
    $avgScore = round(array_sum($scores) / $completedCount, 1);
    $highScore = max($scores);
    $lowScore = min($scores);
}

$totalQuestions = (int)$quiz['total_questions'];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <a href="<?= APP_BASE_URL ?>/teacher/quizzes.php" class="muted small" style="display:inline-block;margin-bottom:0.5rem;text-decoration:none;">&larr; Back to Quizzes</a>
        <h1 class="page-title">Quiz Results: <?= esc($quiz['title']) ?></h1>
        <p class="muted page-subtitle"><?= esc($quiz['course_code']) ?> &mdash; <?= esc($quiz['course_title']) ?></p>
    </div>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="stat blue" style="padding: 1rem; border-radius: var(--radius-sm);">
        <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Total Submissions</div>
        <div style="font-size:1.5rem; font-weight:800;"><?= $completedCount ?></div>
    </div>
    <div class="stat green" style="padding: 1rem; border-radius: var(--radius-sm);">
        <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Average Score</div>
        <div style="font-size:1.5rem; font-weight:800;"><?= $completedCount > 0 ? $avgScore : '—' ?> / <?= $totalQuestions ?></div>
    </div>
    <div class="stat gold" style="padding: 1rem; border-radius: var(--radius-sm);">
        <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Highest Score</div>
        <div style="font-size:1.5rem; font-weight:800;"><?= $completedCount > 0 ? $highScore : '—' ?> / <?= $totalQuestions ?></div>
    </div>
    <div class="stat" style="padding: 1rem; border-radius: var(--radius-sm);">
        <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Lowest Score</div>
        <div style="font-size:1.5rem; font-weight:800;"><?= $completedCount > 0 ? $lowScore : '—' ?> / <?= $totalQuestions ?></div>
    </div>
</div>

<div class="panel">
    <h3 class="panel-title" style="margin-bottom: 1rem;">Student Submissions</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Completed At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attempts)): ?>
                    <tr>
                        <td colspan="6" class="muted" style="text-align:center; padding:2rem;">No students have attempted this quiz yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attempts as $att): ?>
                        <?php
                            $isCompleted = $att['completed_at'] !== null;
                            $score = (int)$att['score'];
                            $pct = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100) : 0;
                            // Color code the percentage
                            if ($pct >= 80) {
                                $pctClass = 'green';
                            } elseif ($pct >= 50) {
                                $pctClass = 'amber';
                            } else {
                                $pctClass = 'red';
                            }
                        ?>
                        <tr>
                            <td><span class="pill muted" style="font-family:monospace;"><?= esc($att['institutional_id']) ?></span></td>
                            <td><?= esc($att['first_name'] . ' ' . $att['last_name']) ?></td>
                            <td>
                                <?php if ($isCompleted): ?>
                                    <strong><?= $score ?></strong> / <?= $totalQuestions ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isCompleted): ?>
                                    <span class="pill <?= $pctClass ?>"><?= $pct ?>%</span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isCompleted): ?>
                                    <span class="pill green">Completed</span>
                                <?php else: ?>
                                    <span class="pill amber">In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isCompleted): ?>
                                    <?= date('M j, Y g:i A', strtotime($att['completed_at'])) ?>
                                <?php else: ?>
                                    <span class="muted">Started <?= date('M j, Y g:i A', strtotime($att['started_at'])) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
