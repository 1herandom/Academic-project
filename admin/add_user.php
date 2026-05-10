<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Academic Admin');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? '';
    
    // Check required basic fields
    if ($first === '' || $last === '' || !in_array($role, ['Academic Admin','Teacher','Student'], true)) {
        flash_set('error', 'Please fill in all required fields.');
        redirect('/admin/add_user.php');
    }

    $course_id = (int)($_POST['course_id'] ?? 0);

    // For Teacher and Student, course_id is mandatory
    if (($role === 'Teacher' || $role === 'Student') && $course_id <= 0) {
        flash_set('error', 'A course must be selected for Teachers and Students.');
        redirect('/admin/add_user.php');
    }

    // Retrieve course code if a course is selected
    $course_code = '';
    if ($course_id > 0) {
        $courseStmt = $pdo->prepare("SELECT course_code FROM courses WHERE id = ?");
        $courseStmt->execute([$course_id]);
        $course_code = $courseStmt->fetchColumn();
    }

    // Auto-generate Institutional ID
    $studentCode = null;
    $teacherCode = null;

    if ($role === 'Student') {
        $studentCode = unique_code(4, 'student_code', 'students');
        $institutional_id = $course_code . $studentCode;
    } elseif ($role === 'Teacher') {
        $teacherCode = unique_code(4, 'teacher_code', 'teachers');
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
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
    $email = '';

    if ($role === 'Academic Admin') {
        $email = build_email($first, $last, $role);
        $stmt = $pdo->prepare("INSERT INTO admins (institutional_id, first_name, last_name, email, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, 1, 'active')");
        $stmt->execute([$institutional_id, $first, $last, $email, $hash]);
    } else if ($role === 'Teacher') {
        $email = build_email($first, $last, $role, $teacherCode, '');
        $stmt = $pdo->prepare("INSERT INTO teachers (institutional_id, first_name, last_name, email, teacher_code, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, ?, 1, 'active')");
        $stmt->execute([$institutional_id, $first, $last, $email, $teacherCode, $hash]);
        $teacherId = $pdo->lastInsertId();
        
        if ($course_id > 0) {
            $pdo->prepare("UPDATE courses SET teacher_user_id = ? WHERE id = ?")->execute([$teacherId, $course_id]);
        }
    } else {
        $email = strtolower($institutional_id) . '@smart.edu.no';
        $stmt = $pdo->prepare("INSERT INTO students (institutional_id, first_name, last_name, email, student_code, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, ?, 1, 'active')");
        $stmt->execute([$institutional_id, $first, $last, $email, $studentCode, $hash]);
        $studentId = $pdo->lastInsertId();
        
        if ($course_id > 0) {
            $pdo->prepare("INSERT INTO enrollments (course_id, student_user_id) VALUES (?, ?)")->execute([$course_id, $studentId]);
        }
    }

    flash_set('success', "User created. Email: {$email}");
    $_SESSION['temp_password'] = $tempPassword;
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
            <label id="course_wrap" class="d-none"><span class="small">Course (Must for Teacher & Student)</span>
                <select name="course_id" id="course_select" class="input">
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= esc($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="notice">
            Teacher email format: <strong>full.name + teacher id + @smart.edu.no</strong><br>
            Student email format: <strong>course id + 4digit intitution id + @smart.edu.no</strong>
        </div>
        <div class="form-actions mt-14">
            <button class="btn" type="submit">Create User</button>
        </div>
    </form>

    <div class="panel">
        <h3 class="panel-title">Account Rules</h3>
        <p class="small">Institutional IDs are unique across the entire database and auto-generated for all roles.</p>
        <p class="small">Teacher IDs are generated as 4-digit codes. Student IDs are generated using the Course ID and a 4-digit code.</p>
        <p class="small">Temporary passwords are issued on first login and can be changed later.</p>
    </div>
</div>

</div>

<script>
document.getElementById('role_select').addEventListener('change', function() {
    const role = this.value;
    const courseWrap = document.getElementById('course_wrap');
    const courseSelect = document.getElementById('course_select');

    if (role === 'Student') {
        courseWrap.classList.remove('d-none');
        courseSelect.required = true;
    } else if (role === 'Teacher') {
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
