<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

$pdo = db();

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) {
    flash_set('error', 'No student specified.');
    redirect('/admin/performance.php');
}

// Fetch student info
$studentStmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch();

if (!$student) {
    flash_set('error', 'Student not found.');
    redirect('/admin/performance.php');
}

// Fetch enrolled courses and aggregated stats per course
$coursesStmt = $pdo->prepare("
    SELECT 
        c.id as course_id, c.course_code, c.course_title,
        (SELECT COUNT(*) FROM attendance_records ar 
         JOIN attendance_sessions asess ON asess.id = ar.attendance_session_id
         WHERE ar.student_user_id = ? AND asess.course_id = c.id) as total_att,
        (SELECT COUNT(*) FROM attendance_records ar 
         JOIN attendance_sessions asess ON asess.id = ar.attendance_session_id
         WHERE ar.student_user_id = ? AND ar.status = 'Present' AND asess.course_id = c.id) as present_att,
        (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) as total_assign,
        (SELECT COUNT(*) FROM submissions sub 
         JOIN assignments a2 ON a2.id = sub.assignment_id
         WHERE sub.student_user_id = ? AND a2.course_id = c.id) as total_sub,
        (SELECT AVG(score) FROM quiz_attempts qa 
         JOIN quizzes q ON q.id = qa.quiz_id
         WHERE qa.student_user_id = ? AND q.course_id = c.id) as avg_quiz_score
    FROM courses c
    JOIN enrollments e ON e.course_id = c.id
    WHERE e.student_user_id = ?
    ORDER BY c.course_code
");
$coursesStmt->execute([$studentId, $studentId, $studentId, $studentId, $studentId]);
$courses = $coursesStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:12px;">
            <a href="performance.php" class="btn secondary sm" style="padding:8px 12px; border-radius:12px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="page-title" style="margin:0; font-size:2rem; font-weight:800;">Student Stats: <?= esc($student['first_name'] . ' ' . $student['last_name']) ?></h1>
        </div>
        <p class="muted page-subtitle" style="font-size:1rem; opacity:0.8;">
            <span class="pill muted" style="background:rgba(128,128,128,0.1); border:1px solid var(--border-color);"><?= esc($student['institutional_id']) ?></span>
            &nbsp;·&nbsp; <?= esc($student['email']) ?> 
            &nbsp;·&nbsp; <span style="color:var(--herald-green); font-weight:600;"><?= esc($student['cluster_group'] ?: 'General Group') ?></span>
        </p>
    </div>
</div>

<?php if (!empty($courses)): ?>
<!-- ── Visualization Section ────────────────────────────────────────────────── -->
<div class="grid-2 mt-6" style="gap:24px; margin-bottom:40px;">
    <div class="card stat-viz-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <h3 class="panel-title" style="margin:0; font-size:1.2rem; font-weight:700;">Overall Attendance</h3>
            <span class="small muted">Across all terms</span>
        </div>
        <div style="height:280px; position:relative; display:flex; align-items:center; justify-content:center;">
            <canvas id="attendanceChart"></canvas>
            <div id="attCenterText" style="position:absolute; text-align:center; pointer-events:none;">
                <div style="font-size:2.5rem; font-weight:900; color:var(--text-main); line-height:1;">0%</div>
                <div class="small muted" style="text-transform:uppercase; letter-spacing:0.1em; font-weight:700; margin-top:4px;">Present</div>
            </div>
        </div>
    </div>
    
    <div class="card stat-viz-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <h3 class="panel-title" style="margin:0; font-size:1.2rem; font-weight:700;">Course Engagement</h3>
            <span class="small muted">Comparison by Course</span>
        </div>
        <div style="height:280px; position:relative;">
            <canvas id="engagementChart"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<div style="margin-bottom:24px; display:flex; align-items:center; gap:12px;">
    <div style="height:2px; flex:1; background:linear-gradient(to right, var(--border-color), transparent);"></div>
    <h2 style="font-size:1.2rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted);">Enrolled Courses Breakdown</h2>
    <div style="height:2px; flex:1; background:linear-gradient(to left, var(--border-color), transparent);"></div>
</div>

<div class="grid-3" style="margin-top:24px;">
    <?php foreach ($courses as $c): 
        $attPct = $c['total_att'] > 0 ? round(($c['present_att'] / $c['total_att']) * 100) : 100;
        $subPct = $c['total_assign'] > 0 ? round(($c['total_sub'] / $c['total_assign']) * 100) : 100;
        $quizScore = $c['avg_quiz_score'] !== null ? round($c['avg_quiz_score'], 1) : null;
        $isAtRisk = $attPct < 75 || $subPct < 60;
    ?>
    <div class="card student-course-card <?= $isAtRisk ? 'at-risk' : '' ?>">
        <div class="panel-header" style="border-bottom:1px solid var(--border-color); margin-bottom:20px; padding-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div class="pill muted small font-mono" style="background:var(--bg-color);"><?= esc($c['course_code']) ?></div>
                <?php if ($isAtRisk): ?>
                    <span class="pill red" style="font-size:10px; padding:2px 8px;">AT RISK</span>
                <?php endif; ?>
            </div>
            <h3 class="panel-title" style="margin:8px 0 0; font-size:1.2rem; text-align:left;"><?= esc($c['course_title']) ?></h3>
        </div>

        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Attendance -->
            <div class="metric-row">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span class="small font-bold" style="color:var(--text-muted);">Attendance</span>
                    <span class="font-bold" style="color:<?= $attPct < 75 ? 'var(--herald-red)' : 'var(--herald-green)' ?>"><?= $attPct ?>%</span>
                </div>
                <div class="progress-wrap">
                    <div class="progress <?= $attPct < 75 ? 'crit' : 'green' ?>" style="height:10px;">
                        <span style="width:<?= $attPct ?>%"></span>
                    </div>
                </div>
                <div class="small muted mt-2" style="text-align:left;"><?= $c['present_att'] ?> / <?= $c['total_att'] ?> sessions</div>
            </div>

            <!-- Assignments -->
            <div class="metric-row">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span class="small font-bold" style="color:var(--text-muted);">Assignments</span>
                    <span class="font-bold" style="color:<?= $subPct < 60 ? 'var(--herald-amber)' : 'var(--herald-green)' ?>"><?= $subPct ?>%</span>
                </div>
                <div class="progress-wrap">
                    <div class="progress <?= $subPct < 60 ? 'warn' : 'green' ?>" style="height:10px;">
                        <span style="width:<?= $subPct ?>%"></span>
                    </div>
                </div>
                <div class="small muted mt-2" style="text-align:left;"><?= $c['total_sub'] ?> / <?= $c['total_assign'] ?> submitted</div>
            </div>

            <!-- Quizzes -->
            <div style="padding-top:16px; border-top:1px dashed var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <span class="small font-bold" style="color:var(--text-muted);">Quiz Avg</span>
                <?php if ($quizScore !== null): ?>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="quiz-score-indicator" style="background:<?= $quizScore < 50 ? 'var(--herald-red)' : 'var(--herald-green)' ?>"></div>
                        <span class="font-bold" style="font-size:1.1rem;"><?= $quizScore ?></span>
                    </div>
                <?php else: ?>
                    <span class="muted small italic">No attempts</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<style>
.stat-viz-card {
    text-align: left;
    padding: 32px !important;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
}
.student-course-card {
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: var(--surface-color);
}
.student-course-card:hover {
    transform: translateY(-8px) scale(1.02);
    border-color: var(--herald-green);
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}
.student-course-card.at-risk {
    border-color: rgba(228, 145, 166, 0.3);
}
.student-course-card.at-risk:hover {
    border-color: var(--herald-red);
}
.progress-wrap {
    background: rgba(0,0,0,0.2);
    border-radius: 99px;
    padding: 2px;
}
.quiz-score-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    box-shadow: 0 0 10px currentColor;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($courses)): 
    $courseLabels = array_column($courses, 'course_code');
    $attData      = array_map(fn($c) => $c['total_att'] > 0 ? round(($c['present_att'] / $c['total_att']) * 100) : 100, $courses);
    $subData      = array_map(fn($c) => $c['total_assign'] > 0 ? round(($c['total_sub'] / $c['total_assign']) * 100) : 100, $courses);
    
    $totalPres = array_sum(array_column($courses, 'present_att'));
    $totalPoss = array_sum(array_column($courses, 'total_att'));
    $overallAtt = $totalPoss > 0 ? round(($totalPres / $totalPoss) * 100) : 100;
?>

// Set chart defaults to match Herald standard
const isLight = document.documentElement.classList.contains('light-mode');
Chart.defaults.color = isLight ? '#2E6F40' : '#C1E3CE';
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.weight = '600';

// ── Overall Attendance Chart ──────────────────────────────────────────────
const attCenterText = document.querySelector('#attCenterText div');
if (attCenterText) attCenterText.textContent = '<?= $overallAtt ?>%';

const attendanceCanvas = document.getElementById('attendanceChart');
if (attendanceCanvas) {
    new Chart(attendanceCanvas, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent'],
            datasets: [{
                data: [<?= $overallAtt ?>, <?= 100 - $overallAtt ?>],
                backgroundColor: ['#7DFFB2', isLight ? 'rgba(46, 111, 64, 0.1)' : 'rgba(228, 145, 166, 0.15)'],
                hoverBackgroundColor: ['#a0ffd0', isLight ? 'rgba(46, 111, 64, 0.2)' : 'rgba(228, 145, 166, 0.3)'],
                borderWidth: 0,
                weight: 0.5
            }]
        },
        options: {
            cutout: '82%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { padding: 20, usePointStyle: true, font: { size: 12 } }
                }
            },
            animation: {
                animateRotate: true,
                animateScale: true
            }
        }
    });
}

// ── Course Engagement Chart ──────────────────────────────────────────────
const engagementCanvas = document.getElementById('engagementChart');
if (engagementCanvas) {
    new Chart(engagementCanvas, {
        type: 'bar',
        data: {
            labels: <?= json_encode($courseLabels) ?>,
            datasets: [
                {
                    label: 'Attendance %',
                    data: <?= json_encode($attData) ?>,
                    backgroundColor: isLight ? 'rgba(46, 111, 64, 0.6)' : 'rgba(125, 255, 178, 0.7)',
                    hoverBackgroundColor: isLight ? 'rgba(46, 111, 64, 0.8)' : '#7DFFB2',
                    borderRadius: 8,
                    barThickness: 24
                },
                {
                    label: 'Submissions %',
                    data: <?= json_encode($subData) ?>,
                    backgroundColor: isLight ? 'rgba(228, 145, 166, 0.4)' : 'rgba(228, 145, 166, 0.5)',
                    hoverBackgroundColor: '#E491A6',
                    borderRadius: 8,
                    barThickness: 24
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true, 
                    max: 100, 
                    grid: { color: isLight ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0.05)' },
                    ticks: { font: { size: 10 } }
                },
                x: { 
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            },
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { padding: 20, usePointStyle: true, font: { size: 12 } }
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
