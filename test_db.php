<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// Clear previous test
$pdo = db();
$pdo->query("UPDATE admins SET current_session_id=NULL, last_activity=NULL WHERE email='system.admin@smart.edu.np'");

// Fake Browser A
session_id('sessA');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['email'] = 'system.admin@smart.edu.np';
$_POST['password'] = 'Admin@123!';

$tables = ['admins' => 'Academic Admin'];
$user = $pdo->query("SELECT * FROM admins WHERE email='system.admin@smart.edu.np'")->fetch();
$user['table'] = 'admins';
$user['role'] = 'Academic Admin';

// This is essentially what handle_login() does:
// 1. Check existing
$stmt = db()->prepare("SELECT current_session_id, last_activity FROM {$user['table']} WHERE id = ?");
$stmt->execute([$user['id']]);
$sessionInfo = $stmt->fetch();

if ($sessionInfo && !empty($sessionInfo['current_session_id'])) {
    echo "Rejecting sessA\n";
} else {
    echo "Allowing sessA\n";
    login_user($user, false);
}

// See DB
print_r($pdo->query("SELECT current_session_id, last_activity FROM admins WHERE id=".$user['id'])->fetch());

// Fake Browser B
session_id('sessB'); // Switch to Browser B

$stmt = db()->prepare("SELECT current_session_id, last_activity FROM {$user['table']} WHERE id = ?");
$stmt->execute([$user['id']]);
$sessionInfo = $stmt->fetch();

if ($sessionInfo && !empty($sessionInfo['current_session_id'])) {
    $lastActivity = new DateTime($sessionInfo['last_activity'] ?? '2000-01-01');
    $now = new DateTime();
    $diff = $now->getTimestamp() - $lastActivity->getTimestamp();
    
    if ($diff < 1800) {
        echo "Rejecting sessB\n";
        db()->prepare("UPDATE {$user['table']} SET current_session_id=NULL, last_activity=NULL WHERE id=?")->execute([$user['id']]);
    }
}

// See DB
print_r($pdo->query("SELECT current_session_id, last_activity FROM admins WHERE id=".$user['id'])->fetch());

// Now Fake Browser A makes a request
session_id('sessA'); // Switch back to Browser A
$stmt = db()->prepare("SELECT current_session_id FROM {$user['table']} WHERE id = ?");
$stmt->execute([$user['id']]);
$db_session_id = $stmt->fetchColumn();

if ($db_session_id !== session_id()) {
    echo "Logging out sessA\n";
    logout_user();
}

