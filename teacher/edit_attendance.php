<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

/*
|--------------------------------------------------------------------------
|Feature By | Bipin Guragain: Edit Attendance and session Validation.
|--------------------------------------------------------------------------
*/

$pdo       = db();
$teacherId = current_user()['id'];
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    flash_set('error', 'No session specified.');
    redirect('/teacher/attendance.php');
}

// Verify the teacher owns this session
$sessionStmt = $pdo->prepare("
    SELECT s.id, s.session_date, s.session_type,
           c.id AS course_id, c.course_code, c.course_title
    FROM   attendance_sessions s
    JOIN   courses c ON c.id = s.course_id
    WHERE  s.id = ?
      AND (s.teacher_user_id = ? OR c.teacher_user_id = ?)
");
$sessionStmt->execute([$sessionId, $teacherId, $teacherId]);
$session = $sessionStmt->fetch();

if (!$session) {
    flash_set('error', 'Session not found or access denied.');
    redirect('/teacher/attendance.php');
}

// ── Save attendance ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $studentIds = $_POST['student_id'] ?? [];
    $statusMap  = $_POST['status'] ?? [];

    $recStmt = $pdo->prepare("SELECT student_user_id FROM attendance_records WHERE attendance_session_id = ?");
    $recStmt->execute([$sessionId]);
    $existingRecords = array_flip($recStmt->fetchAll(PDO::FETCH_COLUMN, 0));

    $updateStmt = $pdo->prepare("
        UPDATE attendance_records
        SET    status = ?, recorded_at = UTC_TIMESTAMP()
        WHERE  attendance_session_id = ? AND student_user_id = ?
    ");
    $insertStmt = $pdo->prepare("
        INSERT INTO attendance_records (attendance_session_id, student_user_id, status, recorded_at)
        VALUES (?, ?, ?, UTC_TIMESTAMP())
    ");

    $pdo->beginTransaction();
    foreach ($studentIds as $sid) {
        $sid    = (int)$sid;
        $status = ($statusMap[$sid] ?? 'Absent') === 'Present' ? 'Present' : 'Absent';
        if (isset($existingRecords[$sid])) {
            $updateStmt->execute([$status, $sessionId, $sid]);
        } else {
            $insertStmt->execute([$sessionId, $sid, $status]);
        }
    }
    $pdo->commit();

    flash_set('success', 'Attendance saved successfully.');
    redirect('/teacher/edit_attendance.php?session_id=' . $sessionId);
}

// ── Load students enrolled in the course ─────────────────────────────────────
$studentStmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.institutional_id
    FROM   enrollments e
    JOIN   students u ON u.id = e.student_user_id
    WHERE  e.course_id = ? AND u.status = 'active'
    ORDER  BY u.first_name, u.last_name
");
$studentStmt->execute([$session['course_id']]);
$students = $studentStmt->fetchAll();

// Existing attendance records for this session
$recStmt = $pdo->prepare("SELECT student_user_id, status FROM attendance_records WHERE attendance_session_id = ?");
$recStmt->execute([$sessionId]);
$existingRecords = $recStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$displayDate  = date('M d, Y  h:i A', strtotime($session['session_date']));
$sessionLabel = ['L' => 'Lecture', 'T' => 'Tutorial', 'W' => 'Workshop'][$session['session_type']] ?? $session['session_type'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Edit Attendance</h1>
        <p class="muted page-subtitle">
            <?= esc($session['course_code'] . ' — ' . $session['course_title']) ?>
            &nbsp;·&nbsp; <?= esc($sessionLabel) ?>
            &nbsp;·&nbsp; <?= esc($displayDate) ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="<?= APP_BASE_URL ?>/teacher/annotate_attendance.php?session_id=<?= $sessionId ?>" class="btn secondary">Annotate</a>
        <a href="<?= APP_BASE_URL ?>/teacher/attendance.php" class="btn secondary">← Back to Sessions</a>
    </div>
</div>

<?php if (empty($students)): ?>
    <div class="notice" style="margin-top:20px;">No active students are enrolled in this course yet.</div>
<?php else: ?>

<form class="panel" method="post" data-attendance-grid>
    <input type="hidden" name="save_attendance" value="1">

    <div class="att-toolbar">
        <button class="btn secondary" type="button" data-mark-all-present>Mark All Present</button>
        <div class="counter">Present: <strong data-present-count>0</strong></div>
        <div class="counter">Absent: <strong data-absent-count>0</strong></div>
        <div class="small muted">UTC timestamp applied on save.</div>
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
                <?php foreach ($students as $s):
                    $currStatus = $existingRecords[$s['id']] ?? null;
                ?>
                <tr data-student-row>
                    <td><?= esc($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= esc($s['institutional_id']) ?></td>
                    <td>
                        <label class="toggle">
                            <input type="radio"
                                   name="status[<?= (int)$s['id'] ?>]"
                                   value="Present"
                                   data-status="present"
                                   <?= $currStatus === 'Present' ? 'checked' : '' ?>>
                            <span>Present</span>
                        </label>
                        <input type="hidden" name="student_id[]" value="<?= (int)$s['id'] ?>">
                    </td>
                    <td>
                        <label class="toggle">
                            <input type="radio"
                                   name="status[<?= (int)$s['id'] ?>]"
                                   value="Absent"
                                   data-status="absent"
                                   <?= $currStatus === 'Absent' ? 'checked' : '' ?>>
                            <span>Absent</span>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top:16px;">
        <a href="<?= APP_BASE_URL ?>/teacher/attendance.php" class="btn secondary">Cancel</a>
        <button class="btn success" type="submit">Save Attendance</button>
    </div>
</form>

<?php endif; ?>

<script>
(function () {
    var container = document.querySelector('[data-attendance-grid]');
    if (!container) return;

    function updateCounts() {
        var present = container.querySelectorAll('input[data-status="present"]:checked').length;
        var absent  = container.querySelectorAll('input[data-status="absent"]:checked').length;
        var pEl = document.querySelector('[data-present-count]');
        var aEl = document.querySelector('[data-absent-count]');
        if (pEl) pEl.textContent = present;
        if (aEl) aEl.textContent = absent;
    }

    /* Mark All Present */
    var markBtn = container.querySelector('[data-mark-all-present]');
    if (markBtn) {
        markBtn.addEventListener('click', function () {
            container.querySelectorAll('input[data-status="present"]').forEach(function (r) { r.checked = true; });
            container.querySelectorAll('input[data-status="absent"]').forEach(function (r)  { r.checked = false; });
            updateCounts();
        });
    }

    /* Live counter on radio change */
    container.addEventListener('change', function (e) {
        if (e.target.matches('input[type="radio"]')) updateCounts();
    });

    updateCounts(); /* initialise on load */
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
