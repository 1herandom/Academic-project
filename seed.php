<?php
require_once __DIR__ . '/config.php';

echo "Wiping database and loading clean schema from db.sql...\n";

// Execute the SQL file directly using MySQL binary for a complete wipe and restore
$command = '/opt/lampp/bin/mysql -u ' . DB_USER . (DB_PASS !== '' ? ' -p' . DB_PASS : '') . ' ' . DB_NAME . ' < ' . __DIR__ . '/db.sql';
exec($command, $output, $return_var);

if ($return_var !== 0) {
    die("Error: Failed to import db.sql. Ensure MySQL is running and credentials in config.php are correct.\n");
}

echo "Schema restored successfully.\n\n";

$pdo = db();

// ==========================================
// 1. Seed System Admin (from original seed.php)
// ==========================================
$institutionalId = 'ADMIN001';
$first = 'System';
$last = 'Admin';
$email = 'system.admin@smart.edu.np';
$password = 'Admin@1234';

$fileContent = "Role: System Admin\nEmail: $email\nPassword: $password\n\n";
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO admins (institutional_id, first_name, last_name, email, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, 0, 'active')");
$stmt->execute([$institutionalId, $first, $last, $email, $hash]);

echo "✅ Seeded default System Admin account:\n";
echo "   Email: {$email}\n";
echo "   Password: {$password}\n\n";

// ==========================================
// 2. Setup Test Users (from setup_test_users.php)
// ==========================================
echo "Setting up Test Course and Accounts...\n";

// Create a dummy course for the teacher and student to be attached to
$pdo->query("INSERT INTO courses (course_code, course_title) VALUES ('TEST101', 'Test Course')");
$course_id = $pdo->lastInsertId();

$passTest = 'password123';
$hashTest = password_hash($passTest, PASSWORD_BCRYPT);

// Admin test
$admin_id = 'ADM9999';
$emailAdmin = 'admin@smart.edu.no';
$pdo->prepare("INSERT INTO admins (institutional_id, first_name, last_name, email, password_hash, temp_password, status) VALUES (?, 'Test', 'Admin', ?, ?, 0, 'active')")->execute([$admin_id, $emailAdmin, $hashTest]);
echo "✅ Test Admin:\n   Email: $emailAdmin | Password: $passTest\n";
$fileContent .= "Role: Admin\nEmail: $emailAdmin\nPassword: $passTest\n\n";

// Teacher test
$teacherCode = '9999';
$teacher_id = 'TCH' . $teacherCode;
$emailTeacher = 'teacher@smart.edu.no';
$pdo->prepare("INSERT INTO teachers (institutional_id, first_name, last_name, email, teacher_code, password_hash, temp_password, status) VALUES (?, 'Test', 'Teacher', ?, ?, ?, 0, 'active')")->execute([$teacher_id, $emailTeacher, $teacherCode, $hashTest]);
$t_id = $pdo->lastInsertId();
$pdo->query("UPDATE courses SET teacher_user_id=$t_id WHERE id=$course_id");
echo "✅ Test Teacher:\n   Email: $emailTeacher | Password: $passTest\n";
$fileContent .= "Role: Teacher\nEmail: $emailTeacher\nPassword: $passTest\n\n";

// Student test
$studentCode = '9999';
$student_id = 'TEST101' . $studentCode;
$emailStudent = 'student@smart.edu.no';
$pdo->prepare("INSERT INTO students (institutional_id, first_name, last_name, email, student_code, password_hash, temp_password, status) VALUES (?, 'Test', 'Student', ?, ?, ?, 0, 'active')")->execute([$student_id, $emailStudent, $studentCode, $hashTest]);
$s_id = $pdo->lastInsertId();
$pdo->query("INSERT INTO enrollments (course_id, student_user_id) VALUES ($course_id, $s_id)");
echo "✅ Test Student:\n   Email: $emailStudent | Password: $passTest\n";
$fileContent .= "Role: Student\nEmail: $emailStudent\nPassword: $passTest\n";

file_put_contents(__DIR__ . '/test_credentials.txt', $fileContent);

echo "\n🚀 Done! Database has been wiped, fully seeded, and test users written to test_credentials.txt!\n";
