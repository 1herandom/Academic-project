<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Student');


$pdo = db();
$studentId = current_user()['id'];

$attendance = $pdo->prepare("
    SELECT c.id AS course_id, c.course_code, c.course_title,
           COUNT(DISTINCT s.id) AS total_sessions,
           SUM(CASE WHEN ar.status='Present' THEN 1 ELSE 0 END) AS attended
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    LEFT JOIN attendance_sessions s ON s.course_id = c.id
    LEFT JOIN attendance_records ar ON ar.attendance_session_id = s.id AND ar.student_user_id = e.student_user_id
    WHERE e.student_user_id = ?
    GROUP BY c.id
    ORDER BY c.course_code
");
$attendance->execute([$studentId]);
$courses = $attendance->fetchAll();

function attendance_badge(float $pct): array {
    if ($pct >= 75) return ['green', 'On Track'];
    if ($pct >= 50) return ['amber', 'At Risk'];
    return ['red', 'Critical'];
}
?>
<h1>Student Portal</h1>
<p class="muted">Track your overall attendance and detailed session breakdown by course.</p>

<div class="grid-3">
    <div class="panel">
        <h3 style="margin-top:0;">Attendance Summary</h3>
        <p class="small">View course-wise percentage with accessibility text for all users.</p>
    </div>
    <div class="panel">
        <h3 style="margin-top:0;">Lecture/Tutorial/Workshop</h3>
        <p class="small">Open the details view to see split statistics for L, T, and W.</p>
    </div>
    <div class="panel">
        <h3 style="margin-top:0;">Assignments</h3>
        <p class="small">Submit only before the deadline using the file restrictions enforced by the server.</p>
    </div>
</div>

<div class="grid-2" style="margin-top:20px;">
    <?php foreach ($courses as $c): 
        $held = (int)$c['total_sessions'];
        $attended = (int)$c['attended'];
        $pct = $held > 0 ? round(($attended / $held) * 100, 2) : 0;
        [$cls, $label] = attendance_badge($pct);
        $warning = $pct < 75;
    ?>
    <div class="panel">
        <h3 style="margin-top:0;"><?= esc($c['course_code']) ?> — <?= esc($c['course_title']) ?></h3>
        <div class="small">Sessions held: <?= $held ?> · Sessions attended: <?= $attended ?></div>
        <div style="margin:10px 0 8px;" class="progress"><span style="width: <?= min(100, $pct) ?>%"></span></div>
        <div class="pill <?= $cls ?>"><?= number_format($pct, 1) ?>% — <?= esc($label) ?></div>
        <?php if ($warning): ?>
            <div class="notice" style="margin-top:12px;">Warning: attendance is below the 75% threshold.</div>
        <?php endif; ?>
        <div style="margin-top:12px;">
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/student/attendance.php?course_id=<?= (int)$c['course_id'] ?>">Details</a>
            <a class="btn" href="<?= APP_BASE_URL ?>/student/submissions.php?course_id=<?= (int)$c['course_id'] ?>">Assignments</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
