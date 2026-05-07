<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');

/*
|--------------------------------------------------------------------------
| Feature 1 | Bipin: Select session type (Lecture/Tutorial/Workshop)
| Feature 2 | Bipin: Bulk attendance toggles with mark-all action and UTC save
| Feature 6 | Bipin Guragain: Attendance annotations and faculty comments
|--------------------------------------------------------------------------
*/

$pdo = db();
$teacherId = current_user()['id'];

$courseStmt = $pdo->prepare("SELECT c.id, c.course_code, c.course_title FROM courses c WHERE c.teacher_user_id = ? ORDER BY c.course_code");
$courseStmt->execute([$teacherId]);
$courses = $courseStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$selectedType = $_GET['session_type'] ?? $_POST['session_type'] ?? 'L';
$selectedDate = $_GET['session_date'] ?? $_POST['session_date'] ?? gmdate('Y-m-d\TH:i');

/* ── Handle annotation update on existing records ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_annotation'])) {
    $recordId   = (int)$_POST['record_id'];
    $annotation = trim($_POST['annotation'] ?? '');
    $courseId    = (int)$_POST['course_id'];

    // Verify the teacher owns this record
    $verify = $pdo->prepare("
        SELECT ar.id FROM attendance_records ar
        JOIN attendance_sessions s ON s.id = ar.attendance_session_id
        WHERE ar.id = ? AND s.teacher_user_id = ?
    ");
    $verify->execute([$recordId, $teacherId]);
    if ($verify->fetch()) {
        $upd = $pdo->prepare("UPDATE attendance_records SET annotation = ? WHERE id = ?");
        $upd->execute([$annotation ?: null, $recordId]);
        flash_set('success', 'Annotation updated successfully.');
    } else {
        flash_set('error', 'You are not authorised to edit this record.');
    }
    redirect('/teacher/attendance.php?course_id=' . $courseId . '&tab=history');
}

/* ── Handle new attendance save ── */
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
    $annotationMap = $_POST['annotation'] ?? [];

    try {
        $pdo->beginTransaction();
        $sessionStmt = $pdo->prepare("INSERT INTO attendance_sessions (course_id, teacher_user_id, session_date, session_type) VALUES (?, ?, ?, ?)");
        $sessionStmt->execute([$courseId, $teacherId, $sessionDate, $sessionType]);
        $sessionId = (int)$pdo->lastInsertId();

        $recordStmt = $pdo->prepare("INSERT INTO attendance_records (attendance_session_id, student_user_id, status, annotation, recorded_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())");

        foreach ($studentIds as $studentId) {
            $studentId = (int)$studentId;
            $status = ($statusMap[$studentId] ?? 'Absent') === 'Present' ? 'Present' : 'Absent';
            $annotation = trim($annotationMap[$studentId] ?? '');
            $recordStmt->execute([$sessionId, $studentId, $status, $annotation ?: null]);
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
            JOIN students u ON u.id = e.student_user_id
            WHERE e.course_id = ? AND u.status='active'
            ORDER BY u.first_name, u.last_name
        ");
        $studentStmt->execute([$selectedCourseId]);
        $students = $studentStmt->fetchAll();
    }
}

/* ── Attendance history with annotations ── */
$activeTab = $_GET['tab'] ?? 'take';
$historyRecords = [];
if ($selectedCourse && $activeTab === 'history') {
    $histStmt = $pdo->prepare("
        SELECT s.id AS session_id, s.session_date, s.session_type,
               ar.id AS record_id, ar.status, ar.annotation,
               u.first_name, u.last_name, u.institutional_id
        FROM attendance_sessions s
        JOIN attendance_records ar ON ar.attendance_session_id = s.id
        JOIN students u ON u.id = ar.student_user_id
        WHERE s.course_id = ? AND s.teacher_user_id = ?
        ORDER BY s.session_date DESC, u.first_name, u.last_name
    ");
    $histStmt->execute([$selectedCourseId, $teacherId]);
    $historyRecords = $histStmt->fetchAll();
}

// Group history records by session
$sessionGroups = [];
foreach ($historyRecords as $rec) {
    $key = $rec['session_id'];
    if (!isset($sessionGroups[$key])) {
        $sessionGroups[$key] = [
            'session_date' => $rec['session_date'],
            'session_type' => $rec['session_type'],
            'records' => [],
        ];
    }
    $sessionGroups[$key]['records'][] = $rec;
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
        <label><span class="small">Session Date</span><input class="input" type="datetime-local" name="session_date" value="<?= esc($selectedDate) ?>"></label>
        <div style="display:flex;align-items:end;"><button class="btn" type="submit">Load Students</button></div>
    </div>
</form>

<?php if ($selectedCourse): ?>
<!-- Tab navigation -->
<div class="att-tabs" style="margin-top:20px; margin-bottom:16px; display:flex; gap:8px;">
    <a class="btn sm <?= $activeTab === 'take' ? '' : 'secondary' ?>"
       href="<?= APP_BASE_URL ?>/teacher/attendance.php?course_id=<?= $selectedCourseId ?>&session_type=<?= esc($selectedType) ?>&session_date=<?= esc($selectedDate) ?>&tab=take">
        Take Attendance
    </a>
    <a class="btn sm <?= $activeTab === 'history' ? '' : 'secondary' ?>"
       href="<?= APP_BASE_URL ?>/teacher/attendance.php?course_id=<?= $selectedCourseId ?>&tab=history">
        History & Annotations
    </a>
</div>
<?php endif; ?>

<?php if ($activeTab === 'take' && $selectedCourse && $students): ?>
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
                    <th>Annotation</th>
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
                    <td>
                        <input type="text"
                               class="input annotation-input"
                               name="annotation[<?= (int)$s['id'] ?>]"
                               placeholder="Add comment…"
                               maxlength="500">
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
<?php elseif ($activeTab === 'take' && $selectedCourse): ?>
    <div class="notice" style="margin-top:20px;">No active students are enrolled in this course yet.</div>

<?php elseif ($activeTab === 'history' && $selectedCourse): ?>
<!-- Attendance History with Annotations -->
<?php if (empty($sessionGroups)): ?>
    <div class="notice">No attendance sessions recorded for this course yet.</div>
<?php else: ?>
    <?php foreach ($sessionGroups as $sessId => $sess): ?>
    <div class="panel annotation-panel" style="margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
            <span class="pill <?= $sess['session_type'] === 'L' ? 'green' : ($sess['session_type'] === 'T' ? 'amber' : 'red') ?>" style="font-size:12px;">
                <?= $sess['session_type'] === 'L' ? 'Lecture' : ($sess['session_type'] === 'T' ? 'Tutorial' : 'Workshop') ?>
            </span>
            <strong style="font-size:14px;"><?= date('M j, Y — g:i A', strtotime($sess['session_date'])) ?></strong>
        </div>

        <table class="session-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Annotation</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sess['records'] as $rec): ?>
                <tr>
                    <td><?= esc($rec['first_name'] . ' ' . $rec['last_name']) ?></td>
                    <td><span class="pill muted" style="font-family:monospace;"><?= esc($rec['institutional_id']) ?></span></td>
                    <td>
                        <span class="pill <?= $rec['status'] === 'Present' ? 'green' : 'red' ?>">
                            <?= esc($rec['status']) ?>
                        </span>
                    </td>
                    <td colspan="2">
                        <form method="post" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="update_annotation" value="1">
                            <input type="hidden" name="record_id" value="<?= (int)$rec['record_id'] ?>">
                            <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
                            <input type="text"
                                   class="input annotation-input"
                                   name="annotation"
                                   value="<?= esc($rec['annotation'] ?? '') ?>"
                                   placeholder="Add annotation…"
                                   maxlength="500">
                            <button class="btn sm green" type="submit" title="Save annotation">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
