<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Student');

$user = current_user();
$pdo = db();
$quiz_id = (int)($_GET['id'] ?? 0);

// Validate quiz & enrollment
$quizStmt = $pdo->prepare("
    SELECT q.*, c.course_title 
    FROM quizzes q
    JOIN enrollments e ON e.course_id = q.course_id
    JOIN courses c ON c.id = q.course_id
    WHERE q.id = ? AND e.student_user_id = ?
");
$quizStmt->execute([$quiz_id, $user['id']]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    flash_set('error', 'Quiz not found or not accessible.');
    redirect('/student/quizzes.php');
}

// Check if attempt exists
$attemptStmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_user_id = ?");
$attemptStmt->execute([$quiz_id, $user['id']]);
$attempt = $attemptStmt->fetch();

if ($attempt && $attempt['completed_at']) {
    flash_set('error', 'You have already completed this quiz.');
    redirect('/student/quiz_result.php?id=' . $quiz_id);
}

// Start attempt if none exists
if (!$attempt) {
    $insertAttempt = $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, student_user_id) VALUES (?, ?)");
    $insertAttempt->execute([$quiz_id, $user['id']]);
    $attempt_id = $pdo->lastInsertId();
    
    // re-fetch attempt
    $attemptStmt->execute([$quiz_id, $user['id']]);
    $attempt = $attemptStmt->fetch();
} else {
    $attempt_id = $attempt['id'];
}

$questionsStmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$questionsStmt->execute([$quiz_id]);
$questions = $questionsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process submission
    $answers = $_POST['answers'] ?? [];
    $score = 0;
    $total_questions = count($questions);

    $pdo->beginTransaction();
    try {
        $insertAnswer = $pdo->prepare("INSERT INTO quiz_answers (attempt_id, question_id, selected_option_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE selected_option_id = ?");
        
        foreach ($questions as $q) {
            $q_id = $q['id'];
            $selected_opt = isset($answers[$q_id]) ? (int)$answers[$q_id] : null;
            
            $insertAnswer->execute([$attempt_id, $q_id, $selected_opt, $selected_opt]);

            // check if correct
            if ($selected_opt) {
                $checkOpt = $pdo->prepare("SELECT is_correct FROM question_options WHERE id = ?");
                $checkOpt->execute([$selected_opt]);
                if ($checkOpt->fetchColumn() == 1) {
                    $score++;
                }
            }
        }
        
        // Final score (percentage or raw, let's store raw score / total questions as maybe percentage, or just raw)
        // Let's store raw score
        $updateAttempt = $pdo->prepare("UPDATE quiz_attempts SET score = ?, completed_at = UTC_TIMESTAMP() WHERE id = ?");
        $updateAttempt->execute([$score, $attempt_id]);
        
        $pdo->commit();
        flash_set('success', 'Quiz submitted successfully!');
        redirect('/student/quiz_result.php?id=' . $quiz_id);
    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('error', 'Error saving your answers.');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title"><?= esc($quiz['title']) ?></h1>
        <p class="muted page-subtitle">Course: <?= esc($quiz['course_title']) ?></p>
    </div>
</div>

<div class="panel">
    <?php if (empty($questions)): ?>
        <p>No questions have been added to this quiz.</p>
    <?php else: ?>
        <form method="post" id="quizForm">
            <?php foreach ($questions as $i => $q): ?>
                <?php
                $optStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
                $optStmt->execute([$q['id']]);
                $options = $optStmt->fetchAll();
                ?>
                <div style="margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                    <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">
                        <?= $i+1 ?>. <?= nl2br(esc($q['question_text'])) ?>
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($options as $opt): ?>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt['id'] ?>" required>
                                <span><?= esc($opt['option_text']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn lg full" style="margin-top: 1rem;">Submit Quiz</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
