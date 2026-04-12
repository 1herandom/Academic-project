<?php
require_once __DIR__ . '/config.php';

$pdo = db();
$count = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();

if ($count === 0) {
    $institutionalId = 'ADMIN001';
    $first = 'System';
    $last = 'Admin';
    $email = 'system.admin@smart.edu.np';
    $password = 'Admin@1234';

    $stmt = $pdo->prepare("INSERT INTO admins (institutional_id, first_name, last_name, email, password_hash, temp_password, status) VALUES (?, ?, ?, ?, ?, 0, 'active')");
    $stmt->execute([$institutionalId, $first, $last, $email, password_hash($password, PASSWORD_BCRYPT)]);

    echo "Seeded default admin account.\n";
    echo "Institutional ID: {$institutionalId}\n";
    echo "Password: {$password}\n";
    exit;
}

echo "Admins already exist. No seed needed.\n";
