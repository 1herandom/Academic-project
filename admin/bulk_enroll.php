<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

/*
|--------------------------------------------------------------------------
| Feature By | Rijan Adhikari: CSV batch enrollment and dry run validation
  Feature By | Bipin Guragain: CSRF tokens and other security features
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv'])) {
    $data = json_decode($_POST['export_data'] ?? '[]', true);
    if (!empty($data)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="student_credentials_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($data[0]));
        foreach ($data as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bulk_enroll_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'First Name', 'Last Name', 'Personal Email']);
    fputcsv($out, ['', 'John', 'Doe', 'john.doe@gmail.com']);
    fputcsv($out, ['', 'Jane', 'Smith', 'jane.smith@yahoo.com']);
    fclose($out);
    exit;
}

$pdo = db();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_csv'])) {
    //  CSRF validation
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect('/admin/bulk_enroll.php');
    }
    $courseId    = (int)($_POST['course_id'] ?? 0);
    $dryRun      = !empty($_POST['dry_run']);
    //  Strip HTML/script tags from cluster_group (XSS prevention)
    $clusterGroup = htmlspecialchars(strip_tags(trim($_POST['cluster_group'] ?? '')), ENT_QUOTES, 'UTF-8');
    
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
    $firstIndex = array_search('First Name', array_map('trim', $header), true);
    $lastIndex = array_search('Last Name', array_map('trim', $header), true);
    $personalEmailIndex = array_search('Personal Email', array_map('trim', $header), true);
    
    if ($idIndex === false && ($firstIndex === false || $lastIndex === false)) {
        flash_set('error', 'CSV must contain an "ID" column or "First Name" & "Last Name" columns.');
        redirect('/admin/bulk_enroll.php');
    }

    $valid = [];
    $errors = [];
    $generatedUsers = [];
    $total = 0;

    $checkUser = $pdo->prepare("SELECT id, status FROM students WHERE institutional_id = ? LIMIT 1");
    $checkEnroll = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND student_user_id = ?");
    $insertEnroll = $pdo->prepare("INSERT INTO enrollments (course_id, student_user_id) VALUES (?, ?)");

    while (($row = fgetcsv($handle)) !== false) {
        $total++;
        $institutionalId = $idIndex !== false ? trim($row[$idIndex] ?? '') : '';
        
        $user = false;
        if ($institutionalId !== '') {
            $checkUser->execute([$institutionalId]);
            $user = $checkUser->fetch();
        }

        $userId = null;
        if (!$user) {
            if ($firstIndex === false || $lastIndex === false) {
                $errors[] = "Row {$total}: Missing First Name or Last Name columns to create user.";
                continue;
            }
            $firstName = trim($row[$firstIndex] ?? '');
            $lastName = trim($row[$lastIndex] ?? '');
            $personalEmail = $personalEmailIndex !== false ? trim($row[$personalEmailIndex] ?? '') : '';
            if ($personalEmail === '') $personalEmail = null;

            if ($firstName === '' || $lastName === '') {
                $errors[] = "Row {$total}: Missing First Name or Last Name.";
                continue;
            }

            if (!$dryRun) {
                $studentCode = unique_code(4, 'student_code', 'students');
                $institutionalId = $course['course_code'] . $studentCode;
                $email = strtolower($institutionalId) . '@smart.edu.np';
                $tempPassword = generate_temp_password();
                $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
                $cmd = $pdo->prepare("INSERT INTO students (institutional_id, first_name, last_name, email, personal_email, student_code, password_hash, temp_password, status, cluster_group) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', ?)");
                $cmd->execute([$institutionalId, $firstName, $lastName, $email, $personalEmail, $studentCode, $hash, $clusterGroup ?: null]);
                $userId = (int)$pdo->lastInsertId();
                $generatedUsers[] = [
                    'Name' => $firstName . ' ' . $lastName,
                    'ID' => $institutionalId,
                    'Email' => $email,
                    'Personal Email' => $personalEmail ?? 'N/A',
                    'Password' => $tempPassword,
                    'Cluster/Group' => $clusterGroup
                ];
            } else {
                $userId = 'mock';
            }
        } else {
            if ($user['status'] !== 'active') {
                $errors[] = "Row {$total}: ID {$institutionalId} belongs to an archived student record.";
                continue;
            }
            $userId = $user['id'];
            if (!$dryRun && $clusterGroup !== '') {
                $updateCluster = $pdo->prepare("UPDATE students SET cluster_group = ? WHERE id = ?");
                $updateCluster->execute([$clusterGroup, $userId]);
            }
        }

        if (!$dryRun) {
            $checkEnroll->execute([$courseId, $userId]);
            if ((int)$checkEnroll->fetchColumn() > 0) {
                $errors[] = "Row {$total}: ID {$institutionalId} already enrolled.";
                continue;
            }
        }

        $valid[] = $userId;
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

    if (!$dryRun && !empty($generatedUsers)) {
        $_SESSION['batch_export'] = $generatedUsers;
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

require_once __DIR__ . '/../includes/header.php';
?>
<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 2rem;">
    <div>
        <h1 style="margin-bottom:0.5rem;">CSV Batch Enrollment</h1>
        <p class="muted" style="margin-bottom:0;">Upload a CSV with <strong>First Name</strong> and <strong>Last Name</strong> columns to auto-generate accounts, or include an <strong>ID</strong> to enroll existing students.</p>
    </div>
    <form method="post">
        <input type="hidden" name="download_template" value="1">
        <button class="btn secondary" type="submit">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Download Template
        </button>
    </form>
</div>

<form class="panel" method="post" enctype="multipart/form-data">
    <input type="hidden" name="process_csv" value="1">
    <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">
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
    <div class="form-row">
        <label><span class="small">Cluster/Group (Optional)</span><input class="input" type="text" name="cluster_group" placeholder="e.g. Batch 2024 / Computer Science"></label>
        <div style="display:flex;align-items:center;padding-top:20px;">
            <label style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="dry_run" value="1">
                <span class="small">Dry Run (validate only)</span>
            </label>
        </div>
    </div>
    <button class="btn" type="submit">Process CSV</button>
</form>

<?php if (!empty($_SESSION['batch_export'])): ?>
    <div class="panel" style="margin-top:20px; border-color:var(--success); background:rgba(31,157,85,0.06);">
        <h3 style="margin-top:0;color:var(--success);">Credentials Export Generated</h3>
        <p>New students were created during this batch enrollment. Download their auto-generated credentials now.</p>
        <form method="post" target="_blank" style="margin-top:10px;">
           <input type="hidden" name="export_data" value="<?= esc(json_encode($_SESSION['batch_export'])) ?>">
           <input type="hidden" name="export_csv" value="1">
           <button class="btn success" type="submit">Download CSV File</button>
        </form>
        <?php unset($_SESSION['batch_export']); ?>
    </div>
<?php endif; ?>

<?php if ($report): ?>
    <div class="panel panel-margin">
        <h3 class="panel-title">Summary Report</h3>
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
