<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

$pdo = db();

// AP-44 Security | Feature Maker: Bipin Guragain — CSRF protection for user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid security token.');
        redirect('/admin/add_user.php');
    }
    // AP-44: Sanitize name inputs against XSS
    $first         = htmlspecialchars(strip_tags(trim($_POST['first_name']    ?? '')), ENT_QUOTES, 'UTF-8');
    $last          = htmlspecialchars(strip_tags(trim($_POST['last_name']     ?? '')), ENT_QUOTES, 'UTF-8');
    $role          = $_POST['role']               ?? '';
    $personalEmail = trim($_POST['personal_email'] ?? '');

    if ($first === '' || $last === '' || !in_array($role, ['Academic Admin','Teacher','Student'], true)) {
        flash_set('error', 'Please fill in all required fields.');
        redirect('/admin/add_user.php');
    }

    // Validate personal email
    if ($personalEmail !== '' && !filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Invalid personal email address.');
        redirect('/admin/add_user.php');
    }

    $course_id = (int)($_POST['course_id'] ?? 0);
    if (($role === 'Teacher' || $role === 'Student') && $course_id <= 0) {
        flash_set('error', 'A course must be selected for Teachers and Students.');
        redirect('/admin/add_user.php');
    }

    $course_code = '';
    if ($course_id > 0) {
        $courseStmt = $pdo->prepare("SELECT course_code FROM courses WHERE id = ?");
        $courseStmt->execute([$course_id]);
        $course_code = $courseStmt->fetchColumn();
    }

    $studentCode = null;
    $teacherCode = null;

    if ($role === 'Student') {
        $studentCode      = unique_code(4, 'student_code', 'students');
        $institutional_id = $course_code . $studentCode;
    } elseif ($role === 'Teacher') {
        $teacherCode      = unique_code(4, 'teacher_code', 'teachers');
        $institutional_id = 'TCH' . $teacherCode;
    } else {
        do {
            $rnd = (string)random_int(1000, 9999);
            $institutional_id = 'ADM' . $rnd;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE institutional_id = ?");
            $stmt->execute([$institutional_id]);
        } while ((int)$stmt->fetchColumn() > 0);
    }

    $tempPassword = generate_temp_password();
    $hash         = password_hash($tempPassword, PASSWORD_BCRYPT);
    $loginEmail   = '';
    $table        = $role === 'Academic Admin' ? 'admins' : ($role === 'Teacher' ? 'teachers' : 'students');
    $hasPersonalEmail = column_exists($table, 'personal_email');

    if ($role === 'Academic Admin') {
        $loginEmail = build_email($first, $last, $role);
        if ($hasPersonalEmail) {
            $stmt = $pdo->prepare("INSERT INTO admins (institutional_id, first_name, last_name, email, personal_email, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, ?, 1, 'active')");
            $stmt->execute([$institutional_id, $first, $last, $loginEmail, $personalEmail ?: null, $hash]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO admins (institutional_id, first_name, last_name, email, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, 1, 'active')");
            $stmt->execute([$institutional_id, $first, $last, $loginEmail, $hash]);
        }
    } elseif ($role === 'Teacher') {
        $loginEmail = build_email($first, $last, $role, $teacherCode, '');
        if ($hasPersonalEmail) {
            $stmt = $pdo->prepare("INSERT INTO teachers (institutional_id, first_name, last_name, email, personal_email, teacher_code, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active')");
            $stmt->execute([$institutional_id, $first, $last, $loginEmail, $personalEmail ?: null, $teacherCode, $hash]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO teachers (institutional_id, first_name, last_name, email, teacher_code, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, ?, 1, 'active')");
            $stmt->execute([$institutional_id, $first, $last, $loginEmail, $teacherCode, $hash]);
        }
        $teacherId = $pdo->lastInsertId();
        if ($course_id > 0)
            $pdo->prepare("UPDATE courses SET teacher_user_id = ? WHERE id = ?")->execute([$teacherId, $course_id]);
    } else {
        $loginEmail = strtolower($institutional_id) . '@smart.edu.np';
        if ($hasPersonalEmail) {
            $stmt = $pdo->prepare("INSERT INTO students (institutional_id, first_name, last_name, email, personal_email, student_code, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active')");
            $stmt->execute([$institutional_id, $first, $last, $loginEmail, $personalEmail ?: null, $studentCode, $hash]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO students (institutional_id, first_name, last_name, email, student_code, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, ?, 1, 'active')");
            $stmt->execute([$institutional_id, $first, $last, $loginEmail, $studentCode, $hash]);
        }
        $studentId = $pdo->lastInsertId();
        if ($course_id > 0)
            $pdo->prepare("INSERT INTO enrollments (course_id, student_user_id) VALUES (?, ?)")->execute([$course_id, $studentId]);
    }

    // Auto-send password email if personal email was provided
    $emailSent = false;
    $emailError = '';
    if ($personalEmail !== '') {
        $emailHtml = build_password_reset_email(trim("$first $last"), $tempPassword, $loginEmail);
        $result    = send_smtp_email($personalEmail, trim("$first $last"), 'Your Herald Account Has Been Created', $emailHtml);
        if ($result === true) {
            $emailSent = true;
        } else {
            $emailError = $result;
        }
    }

    $_SESSION['temp_password']   = $tempPassword;
    $_SESSION['new_user_email']  = $loginEmail;
    $_SESSION['email_sent']      = $emailSent;
    $_SESSION['email_error']     = $emailError;
    $_SESSION['personal_email']  = $personalEmail;

    flash_set('success', "User created. Login email: {$loginEmail}");
    redirect('/admin/users.php');
}

$courses = $pdo->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-layout">
    <div>
        <h1 class="page-title">Add User</h1>
        <p class="muted page-subtitle">Create staff and student accounts.</p>
    </div>
    <a href="<?= APP_BASE_URL ?>/admin/users.php" class="btn secondary">Back to List</a>
</div>

<div class="grid-2">
    <form class="panel" method="post">
        <h3 class="panel-title">Create Account</h3>
        <input type="hidden" name="create_user" value="1">
        <!-- AP-44 CSRF | Feature Maker: Bipin Guragain -->
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">
        <div class="form-row">
            <label><span class="small">First Name</span><input class="input" type="text" name="first_name" required></label>
            <label><span class="small">Last Name</span><input class="input" type="text" name="last_name" required></label>
        </div>
        <div class="form-row">
            <label><span class="small">Role</span>
                <select name="role" id="role_select" class="input" required>
                    <option value="">Select Role</option>
                    <option value="Academic Admin">Academic Admin</option>
                    <option value="Teacher">Teacher</option>
                    <option value="Student">Student</option>
                </select>
            </label>
            <label id="course_wrap" class="d-none"><span class="small">Course (required for Teacher &amp; Student)</span>
                <select name="course_id" id="course_select" class="input">
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label style="margin-top:12px;">
            <span class="small">Personal Email <span class="muted">(optional — used to send login credentials)</span></span>
            <input class="input" type="email" name="personal_email" placeholder="user@gmail.com">
        </label>

        <div class="notice mt-8">
            If a personal email is provided, login credentials will be <strong>automatically emailed</strong> to the user on account creation.
        </div>

        <div class="form-actions mt-14">
            <button class="btn" type="submit">Create User</button>
        </div>
    </form>

    <div class="panel">
        <h3 class="panel-title">Account Rules</h3>
        <p class="small">Institutional IDs are auto-generated for all roles.</p>
        <p class="small">Teacher IDs: <code>TCH + 4-digit code</code>. Student IDs: <code>CourseCode + 4-digit code</code>.</p>
        <p class="small">A temporary password is issued on creation. The user must change it on first login.</p>
        <p class="small">Login email format:<br>
            Teacher: <code>first.last{code}@smart.edu.np</code><br>
            Student: <code>{institutionalid}@smart.edu.np</code>
        </p>
    </div>
</div>

<script>
document.getElementById('role_select').addEventListener('change', function () {
    const role = this.value;
    const courseWrap   = document.getElementById('course_wrap');
    const courseSelect = document.getElementById('course_select');
    if (role === 'Student' || role === 'Teacher') {
        courseWrap.classList.remove('d-none');
        courseSelect.required = true;
    } else {
        courseWrap.classList.add('d-none');
        courseSelect.required = false;
        courseSelect.value = '';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
