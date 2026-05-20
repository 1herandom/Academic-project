<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Student');

$user = current_user();
$pdo = db();

$quizzes = $pdo->prepare("
    SELECT q.*, c.course_code, c.course_title,
           qa.score, qa.completed_at
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id
    LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_user_id = ?
    WHERE e.student_user_id = ?
    ORDER BY q.created_at DESC
");
$quizzes->execute([$user['id'], $user['id']]);
$quizzesList = $quizzes->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">My Quizzes</h1>
        <p class="muted page-subtitle">Assessments for your enrolled courses.</p>
    </div>
</div>

<div class="panel">
    <?php if (empty($quizzesList)): ?>
        <div class="text-center" style="padding: 3rem 0;">
            <svg class="muted" style="width: 48px; height: 48px; margin-bottom: 1rem;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <h3 style="margin-bottom: 0.5rem;">No Quizzes Available</h3>
            <p class="muted">Your teachers haven't published any quizzes yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Quiz Title</th>
                        <th>Course</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzesList as $q): ?>
                        <tr>
                            <td><strong><?= esc($q['title']) ?></strong></td>
                            <td><?= esc($q['course_code']) ?></td>
                            <td><?= (int)$q['duration_minutes'] ?> mins</td>
                            <td>
                                <?php if ($q['completed_at']): ?>
                                    <span class="pill green">Completed (Score: <?= (int)$q['score'] ?>)</span>
                                <?php elseif ($q['score'] !== null): ?>
                                    <span class="pill amber">In Progress</span>
                                <?php else: ?>
                                    <span class="pill gray">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($q['completed_at']): ?>
                                    <a href="<?= APP_BASE_URL ?>/student/quiz_result.php?id=<?= $q['id'] ?>" class="btn sm secondary">View Result</a>
                                <?php else: ?>
                                    <a href="<?= APP_BASE_URL ?>/student/take_quiz.php?id=<?= $q['id'] ?>" class="btn sm">Take Quiz</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
