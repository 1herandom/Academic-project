<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

/*
|--------------------------------------------------------------------------
| Feature 3 | Rijan Adhikari: CSV batch enrollment and dry run validation
|--------------------------------------------------------------------------
*/

$pdo = db();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_csv'])) {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $dryRun = !empty($_POST['dry_run']);
    if ($courseId <= 0 || empty($_FILES['csv_file']['tmp_name'])) {
        flash_set('error', 'Select a course and upload a CSV file.');
        redirect('/admin/bulk_enroll.php');
    }

    $courseStmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch();
    if (!$course) {
        flash_set('error', 'Course not found.');
        redirect('/admin/bulk_enroll.php');
    }

    $file = $_FILES['csv_file'];
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        flash_set('error', 'Only .csv files are allowed.');
        redirect('/admin/bulk_enroll.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash_set('error', 'Unable to read CSV file.');
        redirect('/admin/bulk_enroll.php');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        flash_set('error', 'CSV file is empty.');
        redirect('/admin/bulk_enroll.php');
    }

    $idIndex = array_search('ID', array_map('trim', $header), true);
    if ($idIndex === false) {
        flash_set('error', 'CSV must contain an "ID" column.');
        redirect('/admin/bulk_enroll.php');
    }

    $valid = [];
    $errors = [];
    $total = 0;

    $checkUser = $pdo->prepare("SELECT id, role, status FROM users WHERE institutional_id = ? LIMIT 1");
    $checkEnroll = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND student_user_id = ?");
    $insertEnroll = $pdo->prepare("INSERT INTO enrollments (course_id, student_user_id) VALUES (?, ?)");

    while (($row = fgetcsv($handle)) !== false) {
        $total++;
        $institutionalId = trim($row[$idIndex] ?? '');
        if ($institutionalId === '') {
            $errors[] = "Row {$total}: empty ID.";
            continue;
        }

        $checkUser->execute([$institutionalId]);
        $user = $checkUser->fetch();

        if (!$user || $user['role'] !== 'Student' || $user['status'] !== 'active') {
            $errors[] = "Row {$total}: ID {$institutionalId} not found in active student records.";
            continue;
        }

        $checkEnroll->execute([$courseId, $user['id']]);
        if ((int)$checkEnroll->fetchColumn() > 0) {
            $errors[] = "Row {$total}: ID {$institutionalId} already enrolled.";
            continue;
        }

        $valid[] = $user['id'];
    }
    fclose($handle);

    if (!$dryRun) {
        $pdo->beginTransaction();
        try {
            foreach ($valid as $studentUserId) {
                $insertEnroll->execute([$courseId, $studentUserId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    $report = [
        'dry_run' => $dryRun,
        'enrolled' => $dryRun ? 0 : count($valid),
        'validated' => count($valid),
        'errors' => count($errors),
        'error_list' => $errors,
        'course' => $course,
    ];
}

$courses = $pdo->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code")->fetchAll();
?>
<h1>CSV Batch Enrollment</h1>
<p class="muted">Upload a CSV with an <strong>ID</strong> column. The system validates each student before linking them to the selected course.</p>

<form class="panel" method="post" enctype="multipart/form-data">
    <input type="hidden" name="process_csv" value="1">
    <div class="form-row">
        <label><span class="small">Select Subject</span>
            <select class="input" name="course_id" required>
                <option value="">Choose Course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span class="small">CSV File</span><input class="input" type="file" name="csv_file" accept=".csv" required></label>
    </div>
    <div class="form-row one" style="display:flex;align-items:center;gap:10px;">
        <label style="display:flex;align-items:center;gap:10px;">
            <input type="checkbox" name="dry_run" value="1">
            <span class="small">Dry Run (validate only, do not save to database)</span>
        </label>
    </div>
    <button class="btn" type="submit">Process CSV</button>
</form>

<?php if ($report): ?>
    <div class="panel" style="margin-top:20px;">
        <h3 style="margin-top:0;">Summary Report</h3>
        <p><strong><?= esc($report['course']['course_code']) ?></strong> — <?= esc($report['course']['course_title']) ?></p>
        <p><?= $report['dry_run'] ? 'Dry run completed.' : 'Enrollment committed.' ?></p>
        <div class="grid-3">
            <div class="stat"><div class="label">Validated</div><div class="value"><?= (int)$report['validated'] ?></div></div>
            <div class="stat"><div class="label">Students Enrolled</div><div class="value"><?= (int)$report['enrolled'] ?></div></div>
            <div class="stat"><div class="label">Errors Found</div><div class="value"><?= (int)$report['errors'] ?></div></div>
        </div>
        <?php if ($report['error_list']): ?>
            <div class="table-wrap" style="margin-top:16px;">
                <table>
                    <thead><tr><th>Error Detail</th></tr></thead>
                    <tbody>
                        <?php foreach ($report['error_list'] as $err): ?>
                            <tr><td><?= esc($err) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
