<?php
require_once __DIR__ . '/config.php';

echo "Wiping database and loading clean schema from db.sql...\n";

/**
 * Detect the mysql binary path across Linux, Windows, and macOS.
 * Checks common locations for XAMPP, MAMP, WampServer, Laragon, and Homebrew.
 * Falls back to 'mysql' from system PATH.
 */
function findMysqlBinary(): string {
    $os = PHP_OS_FAMILY; // 'Windows', 'Darwin' (macOS), 'Linux'

    $candidates = [];

    if ($os === 'Windows') {
        // Static known paths
        $static = [
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysql.dexe',
            'C:\\laragon\\bin\\mysql\\mysql-8.1.0-winx64\\bin\\mysql.exe',
        ];

        // Dynamic: scan WampServer for any installed MySQL version
        $wampRoots = ['C:\\wamp64\\bin\\mysql', 'C:\\wamp\\bin\\mysql'];
        foreach ($wampRoots as $root) {
            if (is_dir($root)) {
                foreach (glob($root . '\\mysql*', GLOB_ONLYDIR) ?: [] as $versionDir) {
                    $static[] = $versionDir . '\\bin\\mysql.exe';
                }
            }
        }

        // Dynamic: scan Laragon for any installed MySQL version
        $laragonRoot = 'C:\\laragon\\bin\\mysql';
        if (is_dir($laragonRoot)) {
            foreach (glob($laragonRoot . '\\mysql*', GLOB_ONLYDIR) ?: [] as $versionDir) {
                $static[] = $versionDir . '\\bin\\mysql.exe';
            }
        }

        $candidates = $static;

    } elseif ($os === 'Darwin') {
        // macOS: XAMPP, MAMP, MAMP Pro, Homebrew (Intel + Apple Silicon)
        $candidates = [
            '/Applications/XAMPP/xamppfiles/bin/mysql',  // XAMPP for macOS
            '/Applications/MAMP/Library/bin/mysql',
            '/Applications/MAMP PRO/Library/bin/mysql',
            '/usr/local/bin/mysql',     // Homebrew (Intel)
            '/opt/homebrew/bin/mysql',  // Homebrew (Apple Silicon)
        ];
    } else {
        // Linux: XAMPP, system package
        $candidates = [
            '/opt/lampp/bin/mysql',
            '/usr/bin/mysql',
            '/usr/local/bin/mysql',
        ];
    }

    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    // Final fallback: rely on system PATH
    return 'mysql';
}

$mysqlBin = findMysqlBinary();
$sqlFile  = __DIR__ . '/db.sql';

// Build connection flags (host, user, password)
$flags  = '-h ' . escapeshellarg(DB_HOST);
$flags .= ' -u ' . escapeshellarg(DB_USER);
if (DB_PASS !== '') {
    // Use --password= to safely handle special characters without shell interpolation issues
    $flags .= ' --password=' . escapeshellarg(DB_PASS);
}
$flags .= ' ' . escapeshellarg(DB_NAME);

if (PHP_OS_FAMILY === 'Windows') {
    // On Windows cmd.exe, use double-quotes for the binary path and redirect via cmd /c
    $quotedBin = '"' . $mysqlBin . '"';
    $quotedSql = '"' . $sqlFile . '"';
    $command = "cmd /c {$quotedBin} {$flags} < {$quotedSql} 2>&1";
} else {
    // On macOS/Linux, use escapeshellarg for both binary and sql file paths
    $command = escapeshellarg($mysqlBin) . " {$flags} < " . escapeshellarg($sqlFile) . ' 2>&1';
}

exec($command, $output, $return_var);

if ($return_var !== 0) {
    $outputStr = implode("\n", $output);
    die("Error: Failed to import db.sql.\nCommand output: {$outputStr}\nEnsure MySQL is running and credentials in .env / config.php are correct.\n");
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

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO admins (institutional_id, first_name, last_name, email, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, 0, 'active')");
$stmt->execute([$institutionalId, $first, $last, $email, $hash]);

echo "Seeded default System Admin account:\n";
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
$emailAdmin = 'admin@smart.edu.np';
$pdo->prepare("INSERT INTO admins (institutional_id, first_name, last_name, email, password_hash, temp_password, status) VALUES (?, 'Test', 'Admin', ?, ?, 0, 'active')")->execute([$admin_id, $emailAdmin, $hashTest]);
echo "Test Admin:\n   Email: $emailAdmin | Password: $passTest\n";

// Teacher test
$teacherCode = '9999';
$teacher_id = 'TCH' . $teacherCode;
$emailTeacher = 'teacher@smart.edu.np';
$pdo->prepare("INSERT INTO teachers (institutional_id, first_name, last_name, email, teacher_code, password_hash, temp_password, status) VALUES (?, 'Test', 'Teacher', ?, ?, ?, 0, 'active')")->execute([$teacher_id, $emailTeacher, $teacherCode, $hashTest]);
$t_id = $pdo->lastInsertId();
$pdo->query("UPDATE courses SET teacher_user_id=$t_id WHERE id=$course_id");
echo "Test Teacher:\n   Email: $emailTeacher | Password: $passTest\n";

// Student test
$studentCode = '9999';
$student_id = 'TEST101' . $studentCode;
$emailStudent = 'student@smart.edu.np';
$pdo->prepare("INSERT INTO students (institutional_id, first_name, last_name, email, student_code, password_hash, temp_password, status) VALUES (?, 'Test', 'Student', ?, ?, ?, 0, 'active')")->execute([$student_id, $emailStudent, $studentCode, $hashTest]);
$s_id = $pdo->lastInsertId();
$pdo->query("INSERT INTO enrollments (course_id, student_user_id) VALUES ($course_id, $s_id)");
echo "Test Student:\n   Email: $emailStudent | Password: $passTest\n";

echo "\n🚀 Done! Database has been wiped and fully seeded.\n";