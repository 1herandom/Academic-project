<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');

$pdo = db();
$teacherId = current_user()['id'];

$courseStmt = $pdo->prepare("SELECT c.id, c.course_code, c.course_title FROM courses c WHERE c.teacher_user_id = ? ORDER BY c.course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$selectedType = $_GET['session_type'] ?? $_POST['session_type'] ?? 'L';
$selectedDate = $_GET['session_date'] ?? $_POST['session_date'] ?? gmdate('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $courseId = (int)$_POST['course_id'];
    $sessionType = $_POST['session_type'];
    $sessionDate = $_POST['session_date'];

    if (!in_array($sessionType, ['L','T','W'], true)) {
        flash_set('error', 'Invalid session type.');
        redirect('/teacher/attendance.php');
    }

    $verify = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_user_id = ?");
    $verify->execute([$courseId, $teacherId]);
    if (!$verify->fetch()) {
        flash_set('error', 'You are not assigned to that course.');
        redirect('/teacher/attendance.php');
    }

    $studentIds = $_POST['student_id'] ?? [];
    $statusMap = $_POST['status'] ?? [];

    try {
        $pdo->beginTransaction();
        $sessionStmt = $pdo->prepare("INSERT INTO attendance_sessions (course_id, teacher_user_id, session_date, session_type) VALUES (?, ?, ?, ?)");
        $sessionStmt->execute([$courseId, $teacherId, $sessionDate, $sessionType]);
        $sessionId = (int)$pdo->lastInsertId();

        $recordStmt = $pdo->prepare("INSERT INTO attendance_records (attendance_session_id, student_user_id, status, recorded_at) VALUES (?, ?, ?, UTC_TIMESTAMP())");

        foreach ($studentIds as $studentId) {
            $studentId = (int)$studentId;
            $status = ($statusMap[$studentId] ?? 'Absent') === 'Present' ? 'Present' : 'Absent';
            $recordStmt->execute([$sessionId, $studentId, $status]);
        }

        $pdo->commit();
        flash_set('success', 'Attendance saved successfully.');
        redirect('/teacher/attendance.php?course_id=' . $courseId . '&session_type=' . $sessionType . '&session_date=' . $sessionDate);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (str_contains($e->getMessage(), 'unique_attendance')) {
            flash_set('error', 'Duplicate attendance entries are blocked for the same course, date, and session type.');
            redirect('/teacher/attendance.php?course_id=' . $courseId . '&session_type=' . $sessionType . '&session_date=' . $sessionDate);
        }
        throw $e;
    }
}

$students = [];
$selectedCourse = null;
if ($selectedCourseId) {
    $courseVerify = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_user_id = ?");
    $courseVerify->execute([$selectedCourseId, $teacherId]);
    $selectedCourse = $courseVerify->fetch();

    if ($selectedCourse) {
        $studentStmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.institutional_id
            FROM enrollments e
            JOIN users u ON u.id = e.student_user_id
            WHERE e.course_id = ? AND u.status='active'
            ORDER BY u.first_name, u.last_name
        ");
        $studentStmt->execute([$selectedCourseId]);
        $students = $studentStmt->fetchAll();
    }
}
?>
<h1>Attendance Management</h1>
<p class="muted">Choose a course, then select Lecture (L), Tutorial (T), or Workshop (W) before saving.</p>

<form class="panel" method="get">
    <div class="form-row">
        <label><span class="small">Course</span>
            <select class="input" name="course_id" required>
                <option value="">Choose course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['course_code'] . ' - ' . $c['course_title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span class="small">Session Type</span>
            <select class="input" name="session_type">
                <option value="L" <?= $selectedType === 'L' ? 'selected' : '' ?>>Lecture (L)</option>
                <option value="T" <?= $selectedType === 'T' ? 'selected' : '' ?>>Tutorial (T)</option>
                <option value="W" <?= $selectedType === 'W' ? 'selected' : '' ?>>Workshop (W)</option>
            </select>
        </label>
    </div>
    <div class="form-row">
        <label><span class="small">Session Date</span><input class="input" type="date" name="session_date" value="<?= esc($selectedDate) ?>"></label>
        <div style="display:flex;align-items:end;"><button class="btn" type="submit">Load Students</button></div>
    </div>
</form>

<?php if ($selectedCourse && $students): ?>
<form class="panel" method="post" data-attendance-grid>
    <input type="hidden" name="save_attendance" value="1">
    <input type="hidden" name="course_id" value="<?= (int)$selectedCourse['id'] ?>">
    <input type="hidden" name="session_type" value="<?= esc($selectedType) ?>">
    <input type="hidden" name="session_date" value="<?= esc($selectedDate) ?>">

    <div class="att-toolbar">
        <button class="btn secondary" type="button" data-mark-all-present>Mark All Present</button>
        <div class="counter">Present: <strong data-present-count>0</strong></div>
        <div class="counter">Absent: <strong data-absent-count>0</strong></div>
        <div class="small">UTC save timestamp is applied automatically.</div>
    </div>

    <div class="att-grid">
        <table class="session-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Institutional ID</th>
                    <th>Present</th>
                    <th>Absent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= esc($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= esc($s['institutional_id']) ?></td>
                    <td>
                        <label class="toggle">
                            <input type="checkbox" name="status[<?= (int)$s['id'] ?>]" value="Present" data-status="present">
                            <span>Present</span>
                        </label>
                        <input type="hidden" name="student_id[]" value="<?= (int)$s['id'] ?>">
                    </td>
                    <td>
                        <label class="toggle">
                            <input type="checkbox" name="status[<?= (int)$s['id'] ?>]" value="Absent" data-status="absent">
                            <span>Absent</span>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top:16px;">
        <button class="btn success" type="submit">Save Attendance</button>
    </div>
</form>
<?php elseif ($selectedCourse): ?>
    <div class="notice" style="margin-top:20px;">No active students are enrolled in this course yet.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
