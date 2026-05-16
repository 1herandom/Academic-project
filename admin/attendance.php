<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

$pdo = db();

// ── Search / filter params ────────────────────────────────────────────────────
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo   = $_GET['date_to']   ?? '';
$filterType     = $_GET['session_type'] ?? '';

$where  = "1=1";
$params = [];

if ($filterDateFrom !== '') {
    $where   .= " AND DATE(s.session_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $where   .= " AND DATE(s.session_date) <= ?";
    $params[] = $filterDateTo;
}
if (in_array($filterType, ['L','T','W'], true)) {
    $where   .= " AND s.session_type = ?";
    $params[] = $filterType;
}

$sql = "
    SELECT s.id AS session_id, s.session_date, s.session_type,
           c.course_code, c.course_title, c.id AS course_id
    FROM   attendance_sessions s
    JOIN   courses c ON c.id = s.course_id
    WHERE  $where
    ORDER  BY s.session_date DESC
    LIMIT  100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recentSessions = $stmt->fetchAll();

$isFiltered = ($filterDateFrom !== '' || $filterDateTo !== '' || $filterType !== '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Admin Attendance Management</h1>
        <p class="muted page-subtitle">Browse and filter attendance sessions across the institution.</p>
    </div>
    <div>
        <a href="<?= APP_BASE_URL ?>/admin/take_attendance.php" class="btn">Take Attendance</a>
    </div>
</div>

<!-- ── Filter bar ──────────────────────────────────────────────────────────── -->
<form class="panel att-filter-bar" method="get">
    <div class="att-filter-row">
        <label>
            <span class="small">From date</span>
            <input class="input" type="date" name="date_from" value="<?= esc($filterDateFrom) ?>">
        </label>
        <label>
            <span class="small">To date</span>
            <input class="input" type="date" name="date_to" value="<?= esc($filterDateTo) ?>">
        </label>
        <label>
            <span class="small">Session type</span>
            <select class="input" name="session_type">
                <option value="">All types</option>
                <option value="L" <?= $filterType === 'L' ? 'selected' : '' ?>>Lecture (L)</option>
                <option value="T" <?= $filterType === 'T' ? 'selected' : '' ?>>Tutorial (T)</option>
                <option value="W" <?= $filterType === 'W' ? 'selected' : '' ?>>Workshop (W)</option>
            </select>
        </label>
        <div class="att-filter-actions">
            <button class="btn" type="submit">Search</button>
            <?php if ($isFiltered): ?>
                <a href="<?= APP_BASE_URL ?>/admin/attendance.php" class="btn secondary">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- ── Results ─────────────────────────────────────────────────────────────── -->
<div class="card">
    <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 class="panel-title">
            <?= $isFiltered ? 'Search Results' : 'Recent Sessions' ?>
        </h3>
        <span class="muted small"><?= count($recentSessions) ?> session<?= count($recentSessions) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($recentSessions)): ?>
        <p class="muted mt-4">
            <?= $isFiltered ? 'No sessions match your filters.' : 'No sessions found.' ?>
        </p>
    <?php else: ?>
        <div class="table-wrap mt-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSessions as $rs):
                        $displayDate = date('M d, Y  h:i A', strtotime($rs['session_date']));
                    ?>
                    <tr>
                        <td><?= esc($displayDate) ?></td>
                        <td><?= esc($rs['course_code']) ?></td>
                        <td>
                            <span class="type-chip type-<?= strtolower(esc($rs['session_type'])) ?>">
                                <?= esc($rs['session_type']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= APP_BASE_URL ?>/admin/edit_attendance.php?session_id=<?= (int)$rs['session_id'] ?>" class="btn sm secondary">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.att-filter-bar { margin-bottom: 20px; }
.att-filter-row {
    display: flex;
    gap: 14px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.att-filter-row label { flex: 1; min-width: 140px; }
.att-filter-actions { display: flex; gap: 8px; align-items: flex-end; padding-bottom: 1px; }

.type-chip {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .06em;
}
.type-l { background: rgba(99,102,241,.14); color: #4338ca; }
.type-t { background: rgba(245,158,11,.14); color: #b45309; }
.type-w { background: rgba(16,185,129,.14); color: #065f46; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
