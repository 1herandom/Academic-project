<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Student');

/*
|--------------------------------------------------------------------------
| Feature 4 | Bipin: Granular attendance analytics by L/T/W
| Feature X | Annotation display: show teacher comments to the student
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
$annotatedRecords = [];

if ($selectedCourseId) {
    foreach ($courses as $c) {
        if ((int)$c['id'] === $selectedCourseId) {
            $selectedCourse = $c;
            break;
        }
    }
    if ($selectedCourse) {
        // Summary totals
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

        // Annotated records for this student in this course
        $annStmt = $pdo->prepare("
            SELECT ar.status,
                   ar.annotation,
                   ar.annotated_at,
                   s.session_date,
                   s.session_type,
                   CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
            FROM   attendance_records ar
            JOIN   attendance_sessions s ON s.id = ar.attendance_session_id
            LEFT JOIN teachers t ON t.id = ar.annotated_by
            WHERE  ar.student_user_id = ?
              AND  s.course_id = ?
              AND  ar.annotation IS NOT NULL
              AND  ar.annotation <> ''
            ORDER BY s.session_date DESC
        ");
        $annStmt->execute([$studentId, $selectedCourseId]);
        $annotatedRecords = $annStmt->fetchAll();
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
    <div class="panel panel-margin">
        <h3 class="panel-title"><?= esc($selectedCourse['course_code'] . ' - ' . $selectedCourse['course_title']) ?></h3>
        <div class="grid-3">
            <div class="stat"><div class="label">Lecture</div><div class="value"><?= (int)$details['attended_l'] ?>/<?= (int)$details['total_l'] ?></div></div>
            <div class="stat"><div class="label">Tutorial</div><div class="value"><?= (int)$details['attended_t'] ?>/<?= (int)$details['total_t'] ?></div></div>
            <div class="stat"><div class="label">Workshop</div><div class="value"><?= (int)$details['attended_w'] ?>/<?= (int)$details['total_w'] ?></div></div>
        </div>
    </div>

    <!-- ── Teacher Annotations ──────────────────────────────────────────── -->
    <?php if (!empty($annotatedRecords)): ?>
    <div class="panel panel-margin">
        <div class="panel-header">
            <h3 class="panel-title">📝 Teacher Annotations</h3>
        </div>
        <p class="muted small" style="margin-top:8px;">Your faculty member has left the following comments on your attendance records.</p>
        <div style="margin-top:16px;display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($annotatedRecords as $ar):
            $sessionLabel = ['L' => 'Lecture', 'T' => 'Tutorial', 'W' => 'Workshop'][$ar['session_type']] ?? $ar['session_type'];
            $sessionDate  = date('M d, Y', strtotime($ar['session_date']));
            $isAbsent     = $ar['status'] === 'Absent';
        ?>
            <div class="annotation-card <?= $isAbsent ? 'annotation-card-absent' : 'annotation-card-present' ?>">
                <div class="annotation-card-header">
                    <div>
                        <strong><?= esc($sessionLabel) ?></strong>
                        <span class="muted small">&nbsp;·&nbsp; <?= esc($sessionDate) ?></span>
                    </div>
                    <span class="status-chip-sm <?= $isAbsent ? 'chip-absent-sm' : 'chip-present-sm' ?>">
                        <?= esc($ar['status']) ?>
                    </span>
                </div>
                <p class="annotation-body"><?= nl2br(esc($ar['annotation'])) ?></p>
                <?php if ($ar['teacher_name'] || $ar['annotated_at']): ?>
                <div class="annotation-meta muted small">
                    <?php if ($ar['teacher_name']): ?>
                        — <?= esc($ar['teacher_name']) ?>
                    <?php endif; ?>
                    <?php if ($ar['annotated_at']): ?>
                        &nbsp;·&nbsp; <?= esc(date('M d, Y', strtotime($ar['annotated_at']))) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<style>
.annotation-card {
    border-radius: 8px;
    padding: 14px 18px;
    border-left: 4px solid;
}
.annotation-card-absent {
    background: rgba(239,68,68,.06);
    border-color: #ef4444;
}
.annotation-card-present {
    background: rgba(34,197,94,.06);
    border-color: #22c55e;
}
.annotation-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.annotation-body {
    margin: 0 0 6px;
    font-size: .9rem;
    line-height: 1.55;
    color: var(--text, #222);
}
.annotation-meta {
    margin-top: 4px;
    font-style: italic;
}
.status-chip-sm {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .04em;
}
.chip-absent-sm  { background: rgba(239,68,68,.15);  color: #dc2626; }
.chip-present-sm { background: rgba(34,197,94,.15);  color: #16a34a; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

