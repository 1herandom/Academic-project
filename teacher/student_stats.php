<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$pdo = db();
$studentId = (int)($_GET['id'] ?? 0);
$teacherId = (int)current_user()['id'];

if (!$studentId) redirect('/teacher/performance.php');

// Security: Verify student is enrolled in at least one course taught by this teacher
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    WHERE e.student_user_id = ? AND c.teacher_user_id = ?
");
$stmt->execute([$studentId, $teacherId]);
if ((int)$stmt->fetchColumn() === 0) {
    flash_set('error', 'Unauthorized access to student details.');
    redirect('/teacher/performance.php');
}

// Fetch student basic info
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();
if (!$student) redirect('/teacher/performance.php');

// Get teacher's courses
$stmt = $pdo->prepare("SELECT id FROM courses WHERE teacher_user_id = ?");
$stmt->execute([$teacherId]);
$teacherCourseIds = array_column($stmt->fetchAll(), 'id');
$inClause = implode(',', array_fill(0, count($teacherCourseIds), '?'));

// Performance Data (Teacher-Course Specific)
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM attendance_records ar JOIN attendance_sessions asess ON asess.id = ar.attendance_session_id WHERE ar.student_user_id = ? AND asess.course_id IN ($inClause)) as total_att,
        (SELECT COUNT(*) FROM attendance_records ar JOIN attendance_sessions asess ON asess.id = ar.attendance_session_id WHERE ar.student_user_id = ? AND ar.status = 'Present' AND asess.course_id IN ($inClause)) as present_att,
        (SELECT COUNT(*) FROM assignments a JOIN enrollments e ON e.course_id = a.course_id WHERE e.student_user_id = ? AND a.course_id IN ($inClause)) as total_assign,
        (SELECT COUNT(*) FROM submissions sub JOIN assignments a2 ON a2.id = sub.assignment_id WHERE sub.student_user_id = ? AND a2.course_id IN ($inClause)) as total_sub
");
$params = array_merge([$studentId], $teacherCourseIds, [$studentId], $teacherCourseIds, [$studentId], $teacherCourseIds, [$studentId], $teacherCourseIds);
$stmt->execute($params);
$perf = $stmt->fetch();

$attRate = $perf['total_att'] > 0 ? round(($perf['present_att'] / $perf['total_att']) * 100, 1) : 100;
$subRate = $perf['total_assign'] > 0 ? round(($perf['total_sub'] / $perf['total_assign']) * 100, 1) : 100;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title"><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></h1>
        <p class="muted page-subtitle">Detailed academic analytics for <?= esc($student['institutional_id']) ?> in your courses.</p>
    </div>
    <a href="performance.php" class="btn secondary">Back to List</a>
</div>

<div class="grid-3" style="margin-bottom:24px;">
    <div class="stat green">
        <div class="label">Attendance Rate</div>
        <div class="value"><?= $attRate ?>%</div>
        <div class="small muted"><?= $perf['present_att'] ?> / <?= $perf['total_att'] ?> sessions attended</div>
    </div>
    <div class="stat blue">
        <div class="label">Submission Rate</div>
        <div class="value"><?= $subRate ?>%</div>
        <div class="small muted"><?= $perf['total_sub'] ?> / <?= $perf['total_assign'] ?> assignments submitted</div>
    </div>
    <div class="stat <?= $attRate < 75 || $subRate < 60 ? 'red' : 'gold' ?>">
        <div class="label">Academic Status</div>
        <div class="value"><?= $attRate < 75 || $subRate < 60 ? 'At Risk' : 'Healthy' ?></div>
        <div class="small muted">Based on your course requirements</div>
    </div>
</div>

<div class="grid-2">
    <!-- Attendance History (Teacher's Courses Only) -->
    <div class="panel">
        <h3 style="margin-bottom:20px;">Attendance History</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT asess.session_date, c.course_code, ar.status
                        FROM attendance_records ar
                        JOIN attendance_sessions asess ON asess.id = ar.attendance_session_id
                        JOIN courses c ON c.id = asess.course_id
                        WHERE ar.student_user_id = ? AND c.teacher_user_id = ?
                        ORDER BY asess.session_date DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$studentId, $teacherId]);
                    $history = $stmt->fetchAll();
                    foreach ($history as $h): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($h['session_date'])) ?></td>
                            <td><span class="pill muted"><?= esc($h['course_code']) ?></span></td>
                            <td><span class="pill <?= $h['status'] === 'Present' ? 'green' : 'red' ?>"><?= $h['status'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="3" class="muted text-center">No attendance records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Submission Log (Teacher's Courses Only) -->
    <div class="panel">
        <h3 style="margin-bottom:20px;">Recent Submissions</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Assignment</th>
                        <th>Course</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT a.title, c.course_code, sub.submitted_at
                        FROM submissions sub
                        JOIN assignments a ON a.id = sub.assignment_id
                        JOIN courses c ON c.id = a.course_id
                        WHERE sub.student_user_id = ? AND c.teacher_user_id = ?
                        ORDER BY sub.submitted_at DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$studentId, $teacherId]);
                    $subs = $stmt->fetchAll();
                    foreach ($subs as $s): ?>
                        <tr>
                            <td><?= esc($s['title']) ?></td>
                            <td><span class="pill muted"><?= esc($s['course_code']) ?></span></td>
                            <td><?= date('M d', strtotime($s['submitted_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($subs)): ?>
                        <tr><td colspan="3" class="muted text-center">No recent submissions.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
