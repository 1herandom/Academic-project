<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');

/*
|--------------------------------------------------------------------------
| Feature 1 | Bipin: Teacher dashboard for session type attendance and records
|--------------------------------------------------------------------------
*/

$pdo        = db();
$teacherId  = current_user()['id'];

$courseStmt = $pdo->prepare("SELECT c.id, c.course_code, c.course_title FROM courses c WHERE c.teacher_user_id = ? ORDER BY c.course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

$assignmentCount = (int)$pdo->query("SELECT COUNT(*) FROM assignments WHERE created_by = " . (int)$teacherId)->fetchColumn();
$materialCount   = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE created_by = "   . (int)$teacherId)->fetchColumn();
?>

<div class="page-hd">
    <h1>Teacher Hub</h1>
    <p>Manage attendance, assignments, and learning materials for your courses.</p>
</div>

<!-- Stats -->
<div class="grid-3" style="margin-bottom:24px;">

    <div class="stat red">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>
        <span class="label">My Courses</span>
        <div class="value"><?= count($courses) ?></div>
    </div>

    <div class="stat amber">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
        </div>
        <span class="label">Assignments</span>
        <div class="value"><?= $assignmentCount ?></div>
    </div>

    <div class="stat green">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
        </div>
        <span class="label">Materials</span>
        <div class="value"><?= $materialCount ?></div>
    </div>

</div>

<!-- Quick action panels -->
<div class="grid-2" style="margin-bottom:24px;">

    <div class="panel">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(45,198,83,0.12);
                        display:grid;place-items:center;color:var(--herald-green);flex-shrink:0;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 style="margin:0;font-size:16px;font-weight:700;">Attendance</h3>
        </div>
        <p class="small" style="margin-bottom:18px;">Record L / T / W session type attendance for your enrolled students. Bulk mark all present with one click.</p>
        <a class="btn success" href="<?= APP_BASE_URL ?>/teacher/attendance.php">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
            Take Attendance
        </a>
    </div>

    <div class="panel">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(244,162,97,0.12);
                        display:grid;place-items:center;color:var(--herald-amber);flex-shrink:0;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
            </div>
            <h3 style="margin:0;font-size:16px;font-weight:700;">Assignments &amp; Materials</h3>
        </div>
        <p class="small" style="margin-bottom:18px;">Publish tasks with deadlines, add brief files, and share lecture notes and lab resources with students.</p>
        <div class="form-actions">
            <a class="btn amber" href="<?= APP_BASE_URL ?>/teacher/assignments.php">Assignments</a>
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/teacher/materials.php">Materials</a>
        </div>
    </div>

</div>

<?php if (!empty($courses)): ?>
<!-- Course list -->
<div class="panel">
    <h3 style="margin:0 0 16px;font-size:15px;font-weight:700;">
        My Courses
        <span class="pill red" style="margin-left:8px;font-size:11px;"><?= count($courses) ?></span>
    </h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                <tr>
                    <td><span class="pill muted" style="font-family:monospace;"><?= esc($c['course_code']) ?></span></td>
                    <td><?= esc($c['course_title']) ?></td>
                    <td>
                        <div class="form-actions" style="margin-top:0;">
                            <a class="btn sm secondary"
                               href="<?= APP_BASE_URL ?>/teacher/attendance.php?course_id=<?= (int)$c['id'] ?>">
                               Attendance
                            </a>
                            <a class="btn sm secondary"
                               href="<?= APP_BASE_URL ?>/teacher/assignments.php?course_id=<?= (int)$c['id'] ?>">
                               Assignments
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
