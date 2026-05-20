<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

$pdo = db();

$search   = trim($_GET['search'] ?? '');
$courseId = (int)($_GET['course_id'] ?? 0);
$params   = [];

// Fetch courses for dropdown
$courses = $pdo->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code")->fetchAll();

$sql = "
    SELECT 
        s.id, s.first_name, s.last_name, s.institutional_id, s.cluster_group,
        (SELECT COUNT(*) FROM attendance_records ar 
         JOIN attendance_sessions asess ON asess.id = ar.attendance_session_id
         WHERE ar.student_user_id = s.id " . ($courseId ? "AND asess.course_id = $courseId" : "") . ") as total_att,
        (SELECT COUNT(*) FROM attendance_records ar 
         JOIN attendance_sessions asess ON asess.id = ar.attendance_session_id
         WHERE ar.student_user_id = s.id AND ar.status = 'Present' " . ($courseId ? "AND asess.course_id = $courseId" : "") . ") as present_att,
        (SELECT COUNT(*) FROM assignments a 
         JOIN enrollments e ON e.course_id = a.course_id 
         WHERE e.student_user_id = s.id " . ($courseId ? "AND a.course_id = $courseId" : "") . ") as total_assign,
        (SELECT COUNT(*) FROM submissions sub 
         JOIN assignments a2 ON a2.id = sub.assignment_id
         WHERE sub.student_user_id = s.id " . ($courseId ? "AND a2.course_id = $courseId" : "") . ") as total_sub
    FROM students s
    WHERE 1=1
";

if ($courseId) {
    $sql .= " AND EXISTS (SELECT 1 FROM enrollments e2 WHERE e2.student_user_id = s.id AND e2.course_id = ?)";
    $params[] = $courseId;
}

if ($search !== '') {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.institutional_id LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY s.first_name, s.last_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-layout">
    <div>
        <h1 class="page-title">Student Performance</h1>
        <p class="muted page-subtitle">Monitor academic engagement and attendance across the institution.</p>
    </div>
</div>

<form class="panel" method="get" style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; align-items:flex-end;">
    <label style="flex:1; min-width:200px; margin-bottom:0;"><span class="small">Search Students</span>
        <input type="text" name="search" class="input" placeholder="Name or ID..." value="<?= esc($search) ?>">
    </label>
    <label style="width:240px; margin-bottom:0;"><span class="small">Filter by Course</span>
        <select name="course_id" class="input" onchange="this.form.submit()">
            <option value="">All Courses</option>
            <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= esc($c['course_code'] . ' - ' . $c['course_title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="btn" style="min-height:42px;">Filter</button>
    <?php if ($search || $courseId): ?>
        <a href="performance.php" class="btn secondary" style="min-height:42px;">Clear</a>
    <?php endif; ?>
</form>

<div class="grid-4" style="margin-bottom:24px;">
    <div class="stat green">
        <div class="label">Avg Attendance</div>
        <div class="value">
            <?php
            $totalPresent = array_sum(array_column($students, 'present_att'));
            $totalPossible = array_sum(array_column($students, 'total_att'));
            echo $totalPossible > 0 ? round(($totalPresent / $totalPossible) * 100, 1) : 0;
            ?>%
        </div>
    </div>
    <div class="stat blue">
        <div class="label">Submission Rate</div>
        <div class="value">
            <?php
            $totalSub = array_sum(array_column($students, 'total_sub'));
            $totalAssign = array_sum(array_column($students, 'total_assign'));
            echo $totalAssign > 0 ? round(($totalSub / $totalAssign) * 100, 1) : 0;
            ?>%
        </div>
    </div>
    <div class="stat gold">
        <div class="label">Filtered Students</div>
        <div class="value"><?= count($students) ?></div>
    </div>
    <div class="stat red">
        <div class="label">At Risk (Filtered)</div>
        <div class="value">
            <?php
            $low = 0;
            foreach ($students as $s) {
                $attPct = $s['total_att'] > 0 ? ($s['present_att'] / $s['total_att']) : 1;
                $subPct = $s['total_assign'] > 0 ? ($s['total_sub'] / $s['total_assign']) : 1;
                if ($attPct < 0.75 || $subPct < 0.6) $low++;
            }
            echo $low;
            ?>
        </div>
    </div>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Institutional ID</th>
                    <th>Attendance</th>
                    <th>Assignments</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">No students found matching your filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($students as $s): 
                    $attPct = $s['total_att'] > 0 ? round(($s['present_att'] / $s['total_att']) * 100) : 0;
                    $subPct = $s['total_assign'] > 0 ? round(($s['total_sub'] / $s['total_assign']) * 100) : 0;
                    $isLow  = ($s['total_att'] > 0 && $attPct < 75) || ($s['total_assign'] > 0 && $subPct < 60);
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;"><?= esc($s['first_name'] . ' ' . $s['last_name']) ?></div>
                        <div class="small muted"><?= esc($s['cluster_group'] ?: 'No Group') ?></div>
                    </td>
                    <td><span class="pill muted"><?= esc($s['institutional_id']) ?></span></td>
                    <td>
                        <div class="flex-align-center gap-2">
                            <div class="progress <?= $attPct < 75 ? 'crit' : 'green' ?>" style="width:60px; height:6px;">
                                <span style="width:<?= $attPct ?>%"></span>
                            </div>
                            <span class="small font-bold"><?= $attPct ?>%</span>
                        </div>
                        <div class="small muted"><?= $s['present_att'] ?> / <?= $s['total_att'] ?> sessions</div>
                    </td>
                    <td>
                        <div class="flex-align-center gap-2">
                            <div class="progress <?= $subPct < 60 ? 'warn' : 'green' ?>" style="width:60px; height:6px;">
                                <span style="width:<?= $subPct ?>%"></span>
                            </div>
                            <span class="small font-bold"><?= $subPct ?>%</span>
                        </div>
                        <div class="small muted"><?= $s['total_sub'] ?> / <?= $s['total_assign'] ?> submitted</div>
                    </td>
                    <td>
                        <span class="pill <?= $isLow ? 'red' : 'green' ?>">
                            <?= $isLow ? 'At Risk' : 'Good' ?>
                        </span>
                    </td>
                    <td>
                        <a href="student_stats.php?id=<?= (int)$s['id'] ?>" class="btn sm secondary">View Details</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
