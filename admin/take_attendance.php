<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

/*
|--------------------------------------------------------------------------
|  Feature By | Bipin Guragain: Take attendance & Security features
|--------------------------------------------------------------------------
*/
$pdo     = db();
$courses = $pdo->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code")->fetchAll();

// AP-44 Security | Feature Maker: Bipin Guragain — CSRF protection for session creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_session'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect('/admin/take_attendance.php');
    }
    $courseId    = (int)$_POST['course_id'];
    $sessionType = $_POST['session_type'];
    $sessionDate = $_POST['session_date'];

    if (!in_array($sessionType, ['L','T','W'], true)) {
        flash_set('error', 'Invalid session type.');
        redirect('/admin/take_attendance.php');
    }

    $verify = $pdo->prepare("SELECT id, teacher_user_id FROM courses WHERE id = ?");
    $verify->execute([$courseId]);
    $courseObj = $verify->fetch();
    if (!$courseObj) {
        flash_set('error', 'Course not found.');
        redirect('/admin/take_attendance.php');
    }

    $sessionCheck = $pdo->prepare("
        SELECT id FROM attendance_sessions
        WHERE course_id = ? AND session_type = ? AND session_date = ?
    ");
    $sessionCheck->execute([$courseId, $sessionType, $sessionDate]);
    $sessionId = $sessionCheck->fetchColumn();

    if (!$sessionId) {
        $ins = $pdo->prepare("
            INSERT INTO attendance_sessions (course_id, teacher_user_id, session_date, session_type)
            VALUES (?, ?, ?, ?)
        ");
        $ins->execute([$courseId, $courseObj['teacher_user_id'], $sessionDate, $sessionType]);
        $sessionId = (int)$pdo->lastInsertId();
    }

    redirect('/admin/edit_attendance.php?session_id=' . $sessionId);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Take Attendance</h1>
        <p class="muted page-subtitle">Select a course, session type and date — then load the student sheet.</p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/admin/attendance.php" class="btn secondary">← Back to Sessions</a>
    </div>
</div>

<div class="panel">
    <form method="post" id="loadForm">
        <input type="hidden" name="load_session" value="1">
        <!-- AP-44 CSRF | Feature Maker: Bipin Guragain -->
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-row">
            <label>
                <span class="small">Course</span>
                <select class="input" name="course_id" id="courseSelect" required>
                    <option value="">— Choose course —</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>">
                            <?= esc($c['course_code'] . ' — ' . $c['course_title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="small">Session Type</span>
                <select class="input" name="session_type" id="typeSelect">
                    <option value="L">Lecture (L)</option>
                    <option value="T">Tutorial (T)</option>
                    <option value="W">Workshop (W)</option>
                </select>
            </label>
        </div>

        <div class="form-row">
            <label>
                <span class="small">Session Date &amp; Time</span>
                <input class="input" type="datetime-local" name="session_date" id="dateInput"
                       value="<?= esc(gmdate('Y-m-d\TH:i')) ?>" required>
            </label>
            <div style="display:flex;align-items:flex-end;gap:10px;">
                <button class="btn" type="submit">Load Students →</button>
            </div>
        </div>

        <div id="lockNotice" style="display:none;" class="lock-notice">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            A session already exists for this combination. Date and type are locked.
        </div>
    </form>
</div>

<?php if (empty($courses)): ?>
    <div class="notice" style="margin-top:20px;">No courses have been created yet.</div>
<?php endif; ?>

<style>
.lock-notice {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 14px;
    padding: 9px 14px;
    border-radius: 7px;
    background: rgba(245,158,11,.1);
    color: #92400e;
    font-size: .85rem;
    font-weight: 500;
}
.input[readonly] { opacity: .6; cursor: not-allowed; background: var(--surface-2, #f3f4f6); }
select[disabled] { opacity: .6; cursor: not-allowed; }
</style>

<script>
(function () {
    const courseEl = document.getElementById('courseSelect');
    const typeEl   = document.getElementById('typeSelect');
    const dateEl   = document.getElementById('dateInput');
    const notice   = document.getElementById('lockNotice');
    let hiddenType = null, hiddenDate = null;

    async function checkLock() {
        const courseId = courseEl.value;
        const type     = typeEl.value;
        const date     = dateEl.value;
        if (!courseId || !date) { unlock(); return; }
        try {
            const url = '<?= APP_BASE_URL ?>/admin/check_session.php'
                + '?course_id=' + encodeURIComponent(courseId)
                + '&session_type=' + encodeURIComponent(type)
                + '&session_date=' + encodeURIComponent(date);
            const data = await (await fetch(url)).json();
            data.exists ? lock() : unlock();
        } catch (e) { unlock(); }
    }

    function lock() {
        typeEl.disabled = true;
        dateEl.readOnly = true;
        notice.style.display = 'flex';
        if (!hiddenType) {
            hiddenType = Object.assign(document.createElement('input'), { type: 'hidden', name: 'session_type' });
            document.getElementById('loadForm').appendChild(hiddenType);
        }
        hiddenType.value = typeEl.value;
        if (!hiddenDate) {
            hiddenDate = Object.assign(document.createElement('input'), { type: 'hidden', name: 'session_date' });
            document.getElementById('loadForm').appendChild(hiddenDate);
        }
        hiddenDate.value = dateEl.value;
    }

    function unlock() {
        typeEl.disabled = false;
        dateEl.readOnly = false;
        notice.style.display = 'none';
        if (hiddenType) { hiddenType.remove(); hiddenType = null; }
        if (hiddenDate) { hiddenDate.remove(); hiddenDate = null; }
    }

    courseEl.addEventListener('change', checkLock);
    typeEl.addEventListener('change', checkLock);
    dateEl.addEventListener('change', checkLock);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
