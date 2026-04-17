<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Student');

/*
|--------------------------------------------------------------------------
| Feature 4 | Bipin: Granular attendance analytics by L/T/W
|--------------------------------------------------------------------------
*/

$pdo = db();
$studentId = current_user()['id'];
$selectedCourseId = (int)($_GET['course_id'] ?? 0);

$coursesStmt = $pdo->prepare("SELECT c.id, c.course_code, c.course_title FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.student_user_id = ? ORDER BY c.course_code");
$coursesStmt->execute([$studentId]);
$courses = $coursesStmt->fetchAll();

$details = null;
$selectedCourse = null;
if ($selectedCourseId) {
    foreach ($courses as $c) {
        if ((int)$c['id'] === $selectedCourseId) {
            $selectedCourse = $c;
            break;
        }
    }
    if ($selectedCourse) {
        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN s.session_type='L' THEN 1 ELSE 0 END) AS total_l,
                SUM(CASE WHEN s.session_type='T' THEN 1 ELSE 0 END) AS total_t,
                SUM(CASE WHEN s.session_type='W' THEN 1 ELSE 0 END) AS total_w,
                SUM(CASE WHEN s.session_type='L' AND ar.status='Present' THEN 1 ELSE 0 END) AS attended_l,
                SUM(CASE WHEN s.session_type='T' AND ar.status='Present' THEN 1 ELSE 0 END) AS attended_t,
                SUM(CASE WHEN s.session_type='W' AND ar.status='Present' THEN 1 ELSE 0 END) AS attended_w
            FROM attendance_sessions s
            LEFT JOIN attendance_records ar ON ar.attendance_session_id = s.id AND ar.student_user_id = ?
            WHERE s.course_id = ?
        ");
        $stmt->execute([$studentId, $selectedCourseId]);
        $details = $stmt->fetch();
    }
}
?>
<h1>Attendance Details</h1>

<form class="panel" method="get">
    <div class="form-row">
        <label><span class="small">Course</span>
            <select class="input" name="course_id" onchange="this.form.submit()">
                <option value="">Choose course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['course_code'] . ' - ' . $c['course_title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</form>

<?php if ($selectedCourse && $details): ?>
    <div class="panel" style="margin-top:20px;">
        <h3 style="margin-top:0;"><?= esc($selectedCourse['course_code'] . ' - ' . $selectedCourse['course_title']) ?></h3>
        <div class="grid-3">
            <div class="stat"><div class="label">Lecture</div><div class="value"><?= (int)$details['attended_l'] ?>/<?= (int)$details['total_l'] ?></div></div>
            <div class="stat"><div class="label">Tutorial</div><div class="value"><?= (int)$details['attended_t'] ?>/<?= (int)$details['total_t'] ?></div></div>
            <div class="stat"><div class="label">Workshop</div><div class="value"><?= (int)$details['attended_w'] ?>/<?= (int)$details['total_w'] ?></div></div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
