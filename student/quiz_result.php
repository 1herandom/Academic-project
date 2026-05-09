<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Student');

$user = current_user();
$pdo = db();
$quiz_id = (int)($_GET['id'] ?? 0);

$quizStmt = $pdo->prepare("
    SELECT q.*, qa.score, qa.completed_at, c.course_title 
    FROM quizzes q
    JOIN quiz_attempts qa ON qa.quiz_id = q.id
    JOIN courses c ON c.id = q.course_id
    WHERE q.id = ? AND qa.student_user_id = ?
");
$quizStmt->execute([$quiz_id, $user['id']]);
$quiz = $quizStmt->fetch();

if (!$quiz || !$quiz['completed_at']) {
    flash_set('error', 'Result not available.');
    redirect('/student/quizzes.php');
}

$questionsStmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$questionsStmt->execute([$quiz_id]);
$questions = $questionsStmt->fetchAll();
$total = count($questions);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Quiz Result</h1>
        <p class="muted page-subtitle"><?= esc($quiz['title']) ?> - <?= esc($quiz['course_title']) ?></p>
    </div>
    <a href="<?= APP_BASE_URL ?>/student/quizzes.php" class="btn secondary">Back to Quizzes</a>
</div>

<div class="grid-2">
    <div class="stat green quiz-stat">
        <div class="label">Your Score</div>
        <div class="value quiz-score"><?= (int)$quiz['score'] ?> / <?= $total ?></div>
        <p class="muted mt-4">Submitted on <?= date('M j, Y g:i A', strtotime($quiz['completed_at'])) ?></p>
    </div>

    <div class="panel">
        <h3 class="quiz-summary-title">Question Summary</h3>
        <ul class="quiz-list">
            <?php foreach ($questions as $i => $q): ?>
                <?php
                // Get selected answer and correct answer
                $ansStmt = $pdo->prepare("
                    SELECT o.is_correct 
                    FROM quiz_answers qa 
                    JOIN question_options o ON qa.selected_option_id = o.id 
                    WHERE qa.question_id = ? AND qa.attempt_id = (SELECT id FROM quiz_attempts WHERE quiz_id=? AND student_user_id=?)
                ");
                $ansStmt->execute([$q['id'], $quiz_id, $user['id']]);
                $is_correct = $ansStmt->fetchColumn();
                ?>
                <li class="quiz-list-item">
                    <?php if ($is_correct): ?>
                        <span class="quiz-correct">✓</span>
                    <?php else: ?>
                        <span class="quiz-wrong">✗</span>
                    <?php endif; ?>
                    <span>Question <?= $i+1 ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
