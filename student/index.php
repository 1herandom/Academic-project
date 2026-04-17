<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Student');

/*
|--------------------------------------------------------------------------
| Feature 3 | Bipin Guragain: Attendance progress visualization and warnings
| Feature 4 | Bipin Guragain: Lecture/Tutorial/Workshop split details
|--------------------------------------------------------------------------
*/

$pdo       = db();
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

// Overall stats
$totalCourses  = count($courses);
$onTrack       = 0;
$atRisk        = 0;
$critical      = 0;

foreach ($courses as $c) {
    $held    = (int)$c['total_sessions'];
    $attended = (int)$c['attended'];
    $pct = $held > 0 ? round(($attended / $held) * 100, 2) : 0;
    if ($pct >= 75) $onTrack++;
    elseif ($pct >= 50) $atRisk++;
    else $critical++;
}

function attendance_badge(float $pct): array {
    if ($pct >= 75) return ['green', 'On Track',  'green'];
    if ($pct >= 50) return ['amber', 'At Risk',   'warn'];
    return                 ['red',   'Critical',  'crit'];
}
?>

<div class="page-hd">
    <h1>Student Portal</h1>
    <p>Track your attendance, assignments, and academic progress across all enrolled courses.</p>
</div>

<!-- Summary stats -->
<div class="grid-3" style="margin-bottom:24px;">
    <div class="stat green">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <span class="label">On Track</span>
        <div class="value"><?= $onTrack ?></div>
    </div>

    <div class="stat amber">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <span class="label">At Risk</span>
        <div class="value"><?= $atRisk ?></div>
    </div>

    <div class="stat red">
        <div class="stat-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <span class="label">Critical</span>
        <div class="value"><?= $critical ?></div>
    </div>
</div>

<!-- Feature info strip -->
<div class="grid-3" style="margin-bottom:24px;">
    <div class="panel" style="display:flex;gap:12px;align-items:flex-start;">
        <div style="width:36px;height:36px;border-radius:8px;background:rgba(45,198,83,0.12);
                    display:grid;place-items:center;color:var(--herald-green);flex-shrink:0;">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <div>
            <div style="font-size:13px;font-weight:700;margin-bottom:3px;">Attendance Summary</div>
            <p class="small">View course-wise percentage with colour status for all your sessions.</p>
        </div>
    </div>
    <div class="panel" style="display:flex;gap:12px;align-items:flex-start;">
        <div style="width:36px;height:36px;border-radius:8px;background:rgba(244,162,97,0.12);
                    display:grid;place-items:center;color:var(--herald-amber);flex-shrink:0;">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <div>
            <div style="font-size:13px;font-weight:700;margin-bottom:3px;">L / T / W Split</div>
            <p class="small">Open course details to see Lecture, Tutorial, and Workshop breakdowns.</p>
        </div>
    </div>
    <div class="panel" style="display:flex;gap:12px;align-items:flex-start;">
        <div style="width:36px;height:36px;border-radius:8px;background:rgba(230,57,70,0.12);
                    display:grid;place-items:center;color:var(--herald-red);flex-shrink:0;">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
        </div>
        <div>
            <div style="font-size:13px;font-weight:700;margin-bottom:3px;">Assignments</div>
            <p class="small">Submit files before the deadline. Submissions are locked after cut-off.</p>
        </div>
    </div>
</div>

<!-- Per-course attendance cards -->
<?php if (empty($courses)): ?>
<div class="panel text-center" style="padding:48px;">
    <p class="muted">You are not enrolled in any courses yet.</p>
</div>
<?php else: ?>
<div class="grid-2">
    <?php foreach ($courses as $c):
        $held     = (int)$c['total_sessions'];
        $attended = (int)$c['attended'];
        $pct      = $held > 0 ? round(($attended / $held) * 100, 2) : 0;
        [$pillClass, $label, $progressClass] = attendance_badge($pct);
        $warning  = $pct < 75;
    ?>
    <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;
                            color:var(--text-muted);margin-bottom:3px;">
                    <?= esc($c['course_code']) ?>
                </div>
                <h3 style="margin:0;font-size:15px;font-weight:700;">
                    <?= esc($c['course_title']) ?>
                </h3>
            </div>
            <span class="pill <?= $pillClass ?>"><?= esc($label) ?></span>
        </div>

        <div class="small" style="margin-bottom:10px;">
            <?= $attended ?> of <?= $held ?> sessions attended
        </div>

        <div class="progress <?= $progressClass ?>" style="margin-bottom:12px;">
            <span style="width:<?= min(100, $pct) ?>%"></span>
        </div>

        <div style="font-size:22px;font-weight:800;letter-spacing:-0.5px;
                    color:<?= $pct >= 75 ? 'var(--herald-green)' : ($pct >= 50 ? 'var(--herald-amber)' : 'var(--herald-red)') ?>;
                    margin-bottom:<?= $warning ? '10px' : '16px' ?>;">
            <?= number_format($pct, 1) ?>%
        </div>

        <?php if ($warning): ?>
        <div class="notice" style="margin-bottom:14px;font-size:12.5px;">
            ⚠ Attendance below the 75% threshold — attend more sessions to stay on track.
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <a class="btn secondary sm"
               href="<?= APP_BASE_URL ?>/student/attendance.php?course_id=<?= (int)$c['course_id'] ?>">
               Details
            </a>
            <a class="btn sm"
               href="<?= APP_BASE_URL ?>/student/submissions.php?course_id=<?= (int)$c['course_id'] ?>">
               Assignments
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
