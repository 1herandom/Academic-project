<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user = current_user();
$pdo = db();

$quizzes = $pdo->prepare("
    SELECT q.*, c.course_code, c.course_title, 
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count 
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.teacher_user_id = ?
    ORDER BY q.created_at DESC
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
                                <a href="<?= APP_BASE_URL ?>/teacher/quiz_questions.php?id=<?= $q['id'] ?>" class="btn sm secondary">Manage Questions</a>
                                <a href="<?= APP_BASE_URL ?>/teacher/quiz_questions.php?id=<?= $q['id'] ?>#upload-panel" class="btn sm" style="margin-left: 8px;">Upload Questions</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
