<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('UTC');

define('DB_HOST',      '127.0.0.1');
define('DB_NAME',      'smart_edu');
define('DB_USER',      'root');
define('DB_PASS',      '');
define('APP_NAME',     'Herald');
define('APP_BASE_URL', '/test/smartedu');

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function esc(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . APP_BASE_URL . $path);
    exit;
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function is_logged_in(): bool { return !empty($_SESSION['user']); }

function flash_set(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function generate_temp_password(int $length = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $max   = strlen($chars) - 1;
    $pwd   = '';
    for ($i = 0; $i < $length; $i++) $pwd .= $chars[random_int(0, $max)];
    return $pwd;
}

function slugify(string $value): string {
    $value = trim(mb_strtolower($value));
    $value = preg_replace('/[^a-z0-9]+/u', '.', $value) ?? '';
    return trim($value, '.') ?: 'user';
}

function unique_code(int $length, string $column, string $table): string {
    $min = (int) str_pad('1', $length, '0');
    $max = (int) str_repeat('9', $length);
    $pdo = db();
    do {
        $code  = (string) random_int($min, $max);
        $stmt  = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
        $stmt->execute([$code]);
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists);
    return $code;
}

function build_email(string $first, string $last, string $role, string $teacherCode = '', string $studentCode = ''): string {
    $baseName = slugify($first . '.' . $last);
    if ($role === 'Teacher') return $baseName . $teacherCode . '@herald.edu.np';
    if ($role === 'Student') return 'NP' . $studentCode . '@herald.edu.np';
    return $baseName . '@herald.edu.np';
}

function user_display_name(array $user): string {
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

function user_initials(array $user): string {
    $f = strtoupper(substr($user['first_name'] ?? '', 0, 1));
    $l = strtoupper(substr($user['last_name']  ?? '', 0, 1));
    return $f . $l;
}

function safe_filename(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'file';
    return trim($name, '_');
}

function login_user(array $user, bool $remember = false): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'               => $user['id'],
        'institutional_id' => $user['institutional_id'],
        'first_name'       => $user['first_name'],
        'last_name'        => $user['last_name'],
        'role'             => $user['role'],
        'table'            => $user['table'] ?? '',
        'email'            => $user['email'],
        'teacher_code'     => $user['teacher_code']  ?? null,
        'student_code'     => $user['student_code']  ?? null,
        'status'           => $user['status']        ?? 'active',
        'profile_photo'    => $user['profile_photo'] ?? null,
    ];

    if ($remember) {
        $selector  = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $validator);
        $expires   = gmdate('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
        $table     = $user['table'] ?? 'admins';
        $stmt = db()->prepare("UPDATE {$table} SET remember_selector = ?, remember_token_hash = ?, remember_expires_at = ? WHERE id = ?");
        $stmt->execute([$selector, $hash, $expires, $user['id']]);
        setcookie('smartedu_remember', $selector . ':' . $validator, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function logout_user(): void {
    if (!empty($_COOKIE['smartedu_remember'])) {
        [$selector] = array_pad(explode(':', $_COOKIE['smartedu_remember'], 2), 2, '');
        if ($selector !== '') {
            foreach(['admins','teachers','students'] as $t) {
                $stmt = db()->prepare("UPDATE {$t} SET remember_selector = NULL, remember_token_hash = NULL, remember_expires_at = NULL WHERE remember_selector = ?");
                $stmt->execute([$selector]);
            }
        }
        setcookie('smartedu_remember', '', time() - 3600, '/');
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

function attempt_remember_login(): void {
    if (is_logged_in() || empty($_COOKIE['smartedu_remember'])) return;
    [$selector, $validator] = array_pad(explode(':', $_COOKIE['smartedu_remember'], 2), 2, '');
    if ($selector === '' || $validator === '') return;

    $tables = ['admins' => 'Academic Admin', 'teachers' => 'Teacher', 'students' => 'Student'];
    $user = null;
    $role = null;
    $table = null;

    foreach ($tables as $t => $r) {
        $stmt = db()->prepare("SELECT * FROM {$t} WHERE remember_selector = ? AND remember_expires_at > UTC_TIMESTAMP() LIMIT 1");
        $stmt->execute([$selector]);
        if ($u = $stmt->fetch()) {
            $user = $u;
            $role = $r;
            $table = $t;
            break;
        }
    }

    if (!$user) return;
    if (!hash_equals($user['remember_token_hash'] ?? '', hash('sha256', $validator))) return;
    
    $user['role'] = $role;
    $user['table'] = $table;
    login_user($user, false);
}

function require_login(): void {
    attempt_remember_login();
    if (!is_logged_in()) {
        flash_set('error', 'Please log in first.');
        redirect('/index.php');
    }
}

function require_role(array|string $allowedRoles): void {
    require_login();
    $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $role    = current_user()['role'] ?? '';
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 – Herald</title><link rel="stylesheet" href="' . APP_BASE_URL . '/assets/style.css"></head><body style="display:grid;place-items:center;min-height:100vh;"><div class="card centered" style="text-align:center;padding:48px;"><h1 style="font-size:64px;font-weight:900;color:var(--herald-red);margin:0 0 8px;">403</h1><h2 style="margin:0 0 12px;">Access Denied</h2><p class="muted" style="margin:0 0 24px;">You do not have permission to view this page.</p><a class="btn" href="' . APP_BASE_URL . '/dashboard.php">Go to Dashboard</a></div></body></html>';
        exit;
    }
}
?>
