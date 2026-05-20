<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$pdo       = db();
$teacherId = current_user()['id'];
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    flash_set('error', 'No session specified.');
    redirect('/teacher/attendance.php');
}

// Verify the teacher owns / created this session
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

// ── Handle annotation save | AP-44 CSRF | Feature Maker: Bipin Guragain ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_annotations'])) {
    // AP-44: CSRF validation
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token. Please reload and try again.');
        redirect('/teacher/annotate_attendance.php?session_id=' . $sessionId);
    }

    $annotations = $_POST['annotation'] ?? [];   // [record_id => text]

    $updateStmt = $pdo->prepare("
        UPDATE attendance_records
        SET    annotation    = ?,
               annotated_by  = ?,
               annotated_at  = UTC_TIMESTAMP()
        WHERE  id = ?
          AND  attendance_session_id = ?
    ");

    $pdo->beginTransaction();
    foreach ($annotations as $recordId => $text) {
        $recordId = (int)$recordId;
        // AP-44: Sanitize annotation text — strip tags, encode entities
        $text = $text === '' ? null : htmlspecialchars(strip_tags(trim($text)), ENT_QUOTES, 'UTF-8');
        $updateStmt->execute([$text, $teacherId, $recordId, $sessionId]);
    }
    $pdo->commit();

    flash_set('success', 'Annotations saved successfully.');
    redirect('/teacher/annotate_attendance.php?session_id=' . $sessionId);
}

// ── Fetch records with student info ──────────────────────────────────────────
$recordsStmt = $pdo->prepare("
    SELECT ar.id          AS record_id,
           ar.status,
           ar.annotation,
           ar.annotated_at,
           s.id           AS student_id,
           s.first_name,
           s.last_name,
           s.institutional_id
    FROM   attendance_records ar
    JOIN   students s ON s.id = ar.student_user_id
    WHERE  ar.attendance_session_id = ?
    ORDER  BY s.first_name, s.last_name
");
$recordsStmt->execute([$sessionId]);
$records = $recordsStmt->fetchAll();

$displayDate  = date('M d, Y  h:i A', strtotime($session['session_date']));
$sessionLabel = ['L' => 'Lecture', 'T' => 'Tutorial', 'W' => 'Workshop'][$session['session_type']] ?? $session['session_type'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Annotate Attendance</h1>
        <p class="muted page-subtitle">
            <?= esc($session['course_code'] . ' — ' . $session['course_title']) ?>
            &nbsp;·&nbsp; <?= esc($sessionLabel) ?>
            &nbsp;·&nbsp; <?= esc($displayDate) ?>
        </p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/teacher/attendance.php" class="btn secondary">← Back to Sessions</a>
    </div>
</div>

<?php if (empty($records)): ?>
    <div class="notice" style="margin-top:20px;">No attendance records found for this session.</div>
<?php else: ?>

<form method="post">
    <input type="hidden" name="save_annotations" value="1">
    <!-- AP-44 CSRF | Feature Maker: Bipin Guragain -->
    <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">

    <div class="card" style="overflow:visible;">
        <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <h3 class="panel-title" style="margin:0;">Student Records</h3>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="muted small"><?= count($records) ?> student<?= count($records) !== 1 ? 's' : '' ?></span>
                <button class="btn sm secondary" type="button" id="expandAllBtn">Expand All</button>
            </div>
        </div>

        <div class="table-wrap mt-4">
            <table class="table" id="annotationTable">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Annotation</th>
                        <th style="width:110px;">Last Updated</th>
                        <th style="width:80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $rec): ?>
                    <?php
                        $hasAnnotation = !empty($rec['annotation']);
                        $isAbsent      = $rec['status'] === 'Absent';
                    ?>
                    <tr class="annotation-row<?= $isAbsent ? ' absent-row' : '' ?>">
                        <td>
                            <span style="font-weight:500;">
                                <?= esc($rec['first_name'] . ' ' . $rec['last_name']) ?>
                            </span>
                        </td>
                        <td class="muted small"><?= esc($rec['institutional_id']) ?></td>
                        <td>
                            <span class="status-chip <?= $rec['status'] === 'Present' ? 'chip-present' : 'chip-absent' ?>">
                                <?= esc($rec['status']) ?>
                            </span>
                        </td>
                        <td class="annotation-cell">
                            <!-- Collapsed preview -->
                            <div class="annotation-preview" data-row="<?= (int)$rec['record_id'] ?>">
                                <?php if ($hasAnnotation): ?>
                                    <span class="annotation-text-preview"><?= esc(mb_strimwidth($rec['annotation'], 0, 80, '…')) ?></span>
                                <?php else: ?>
                                    <span class="muted small">No annotation</span>
                                <?php endif; ?>
                            </div>
                            <!-- Expanded textarea -->
                            <div class="annotation-editor" data-row="<?= (int)$rec['record_id'] ?>" style="display:none;">
                                <textarea
                                    class="input annotation-textarea"
                                    name="annotation[<?= (int)$rec['record_id'] ?>]"
                                    rows="3"
                                    placeholder="Add a comment or context for this student's attendance…"
                                ><?= esc($rec['annotation'] ?? '') ?></textarea>
                            </div>
                        </td>
                        <td class="muted small">
                            <?= $rec['annotated_at'] ? esc(date('M d, Y', strtotime($rec['annotated_at']))) : '—' ?>
                        </td>
                        <td>
                            <button
                                type="button"
                                class="btn sm secondary annotate-toggle-btn"
                                data-row="<?= (int)$rec['record_id'] ?>"
                                data-state="collapsed"
                            >Annotate</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions" style="padding:16px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;">
            <a href="<?= APP_BASE_URL ?>/teacher/attendance.php" class="btn secondary">Cancel</a>
            <button class="btn success" type="submit">Save Annotations</button>
        </div>
    </div>
</form>

<?php endif; ?>

<style>
/* ── Annotation-specific styles ──────────────────────────────────────────── */
.status-chip {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: .03em;
}
.chip-present {
    background: rgba(34,197,94,.15);
    color: #16a34a;
}
.chip-absent {
    background: rgba(239,68,68,.13);
    color: #dc2626;
}
.absent-row {
    background: rgba(239,68,68,.03);
}
.annotation-textarea {
    width: 100%;
    resize: vertical;
    font-size: .875rem;
    min-height: 72px;
    margin-top: 4px;
}
.annotation-cell {
    min-width: 260px;
}
.annotation-text-preview {
    font-size: .85rem;
    color: var(--text-muted, #888);
    font-style: italic;
}
.annotate-toggle-btn[data-state="expanded"] {
    background: var(--herald-red, #e74c3c);
    color: #fff;
    border-color: transparent;
}
</style>

<script>
(function () {
    // Toggle individual row
    document.querySelectorAll('.annotate-toggle-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row      = btn.dataset.row;
            var preview  = document.querySelector('.annotation-preview[data-row="' + row + '"]');
            var editor   = document.querySelector('.annotation-editor[data-row="' + row + '"]');
            var expanded = btn.dataset.state === 'expanded';

            if (expanded) {
                // Collapse — sync preview text from textarea
                var ta   = editor.querySelector('textarea');
                var prev = preview.querySelector('.annotation-text-preview');
                if (ta.value.trim()) {
                    if (!prev) {
                        prev = document.createElement('span');
                        prev.className = 'annotation-text-preview';
                        preview.innerHTML = '';
                        preview.appendChild(prev);
                    }
                    prev.textContent = ta.value.length > 80
                        ? ta.value.substring(0, 80) + '…'
                        : ta.value;
                } else {
                    preview.innerHTML = '<span class="muted small">No annotation</span>';
                }
                editor.style.display  = 'none';
                preview.style.display = '';
                btn.textContent       = 'Annotate';
                btn.dataset.state     = 'collapsed';
            } else {
                // Expand
                preview.style.display = 'none';
                editor.style.display  = '';
                editor.querySelector('textarea').focus();
                btn.textContent   = 'Collapse';
                btn.dataset.state = 'expanded';
            }
        });
    });

    // Expand / collapse all
    var expandAllBtn = document.getElementById('expandAllBtn');
    var allExpanded  = false;
    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function () {
            allExpanded = !allExpanded;
            document.querySelectorAll('.annotate-toggle-btn').forEach(function (btn) {
                var target = allExpanded ? 'collapsed' : 'expanded';
                if (btn.dataset.state === target) btn.click();
            });
            expandAllBtn.textContent = allExpanded ? 'Collapse All' : 'Expand All';
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
