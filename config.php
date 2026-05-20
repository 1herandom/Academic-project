<?php
/**
 * Feature: Security Helpers & Application Configuration
 * Feature By: Bipin Guragain
 * Feature Name: AP-44 Security Standards – Prepared Statements, XSS & CSRF Helpers
 * Description: All database interactions use PDO prepared statements to prevent
 *              SQL injection. The sanitize_input() helper strips tags and encodes
 *              HTML entities for XSS prevention. CSRF tokens are generated in
 *              header.php and validated in every state-changing POST handler.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('UTC');

// ─── Load .env ───────────────────────────────────────────────
$_envFile = __DIR__ . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        if (!str_contains($_line, '=')) continue;
        [$_k, $_v] = array_map('trim', explode('=', $_line, 2));
        $_v = trim(preg_replace('/#.*$/', '', $_v), " \t\"'");
        if (!isset($_ENV[$_k])) { $_ENV[$_k] = $_v; putenv("{$_k}={$_v}"); }
    }
}
unset($_envFile, $_line, $_k, $_v);

function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

define('DB_HOST',        env('DB_HOST',        '127.0.0.1'));
define('DB_NAME',        env('DB_NAME',        'smart_edu'));
define('DB_USER',        env('DB_USER',        'root'));
define('DB_PASS',        env('DB_PASS',        ''));
define('APP_NAME',       env('APP_NAME',       'Herald'));
define('APP_BASE_URL',   env('APP_BASE_URL',   '/smartedu-v1'));
define('SMTP_HOST',      env('SMTP_HOST',      'smtp.gmail.com'));
define('SMTP_PORT',      (int) env('SMTP_PORT','587'));
define('SMTP_USER',      env('SMTP_USER',      ''));
define('SMTP_PASS',      env('SMTP_PASS',      ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Herald Academic'));
define('GROQ_API_KEY',   env('GROQ_API_KEY',   ''));

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Run automatic migrations on first connection
    ensure_personal_email_columns();
    ensure_session_token_columns();
    return $pdo;
}

function column_exists(string $table, string $column): bool {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function esc(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

/**
 * sanitize_input() – AP-44 Security Standard
 * Feature Maker: Bipin Guragain
 * Strips all HTML/script tags and encodes remaining special characters.
 * Use for any user-submitted string before storing or displaying.
 */
function sanitize_input(?string $value): string {
    return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
}
function redirect(string $path): void { header('Location: ' . APP_BASE_URL . $path); exit; }
function current_user(): ?array {
    static $validatedUser = null;
    if ($validatedUser !== null) return $validatedUser;
    
    if (empty($_SESSION['user'])) return null;
    $user = $_SESSION['user'];
    
    if (empty($user['session_token'])) {
        unset($_SESSION['user']);
        return null;
    }
    
    $table = $user['table'] ?? 'admins';
    try {
        $stmt = db()->prepare("SELECT session_token FROM {$table} WHERE id=?");
        $stmt->execute([$user['id']]);
        $dbToken = $stmt->fetchColumn();
        
        if (empty($dbToken) || $dbToken !== $user['session_token']) {
            unset($_SESSION['user']);
            return null;
        }
    } catch (PDOException $e) {
        // Just in case migration hasn't run yet
    }
    
    $validatedUser = $user;
    return $validatedUser;
}
function is_logged_in(): bool   { return current_user() !== null; }
function flash_set(string $type, string $message): void { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }
function flash_get(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function generate_temp_password(int $length = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $max = strlen($chars) - 1; $pwd = '';
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
        $code = (string) random_int($min, $max);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
        $stmt->execute([$code]);
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists);
    return $code;
}

function ensure_column_exists(string $table, string $column, string $definition): void {
    if (column_exists($table, $column)) {
        return;
    }
    db()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}

function ensure_session_token_columns(): void {
    $cols = [
        'admins'   => 'VARCHAR(64) NULL',
        'teachers' => 'VARCHAR(64) NULL',
        'students' => 'VARCHAR(64) NULL',
    ];
    foreach ($cols as $table => $definition) {
        try {
            ensure_column_exists($table, 'session_token', $definition);
        } catch (PDOException $ex) {
        }
    }
}

function ensure_personal_email_columns(): void {
    $cols = [
        'admins'   => 'personal_email VARCHAR(180) NULL AFTER email',
        'teachers' => 'personal_email VARCHAR(180) NULL AFTER email',
        'students' => 'personal_email VARCHAR(180) NULL AFTER email',
    ];
    foreach ($cols as $table => $definition) {
        try {
            ensure_column_exists($table, 'personal_email', $definition);
        } catch (PDOException $ex) {
            // If schema changes cannot be applied, continue using fallback behavior.
        }
    }
}

function build_email(string $first, string $last, string $role, string $teacherCode = '', string $studentCode = ''): string {
    $baseName = slugify($first . '.' . $last);
    if ($role === 'Teacher') return $baseName . $teacherCode . '@smart.edu.np';
    if ($role === 'Student') return 'NP' . $studentCode . '@smart.edu.np';
    return $baseName . '@smart.edu.np';
}

function user_display_name(array $user): string { return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); }
function user_initials(array $user): string {
    return strtoupper(substr($user['first_name'] ?? '', 0, 1)) . strtoupper(substr($user['last_name'] ?? '', 0, 1));
}
function safe_filename(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'file';
    return trim($name, '_');
}

function login_user(array $user, bool $remember = false, bool $is_remember_me = false): void {
    $table = $user['table'] ?? 'admins';
    
    if (!$is_remember_me) {
        try {
            $stmt = db()->prepare("SELECT session_token FROM {$table} WHERE id=?");
            $stmt->execute([$user['id']]);
            $existingToken = $stmt->fetchColumn();
            
            if (!empty($existingToken)) {
                db()->prepare("UPDATE {$table} SET session_token = NULL WHERE id=?")->execute([$user['id']]);
                logout_user();
                flash_set('error', 'Concurrent login detected. All sessions have been logged off.');
                redirect('/index.php');
                exit;
            }
        } catch (PDOException $e) {}
    }
    
    session_regenerate_id(true);
    $newToken = bin2hex(random_bytes(16));
    try {
        db()->prepare("UPDATE {$table} SET session_token = ? WHERE id=?")->execute([$newToken, $user['id']]);
    } catch (PDOException $e) {}

    $_SESSION['user'] = [
        'id'               => $user['id'],
        'institutional_id' => $user['institutional_id'],
        'first_name'       => $user['first_name'],
        'last_name'        => $user['last_name'],
        'role'             => $user['role'],
        'table'            => $table,
        'email'            => $user['email'],
        'teacher_code'     => $user['teacher_code']  ?? null,
        'student_code'     => $user['student_code']  ?? null,
        'status'           => $user['status']        ?? 'active',
        'profile_photo'    => $user['profile_photo'] ?? null,
        'session_token'    => $newToken,
    ];
    if ($remember) {
        $selector  = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $validator);
        $expires   = gmdate('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
        $table     = $user['table'] ?? 'admins';
        db()->prepare("UPDATE {$table} SET remember_selector=?,remember_token_hash=?,remember_expires_at=? WHERE id=?")
            ->execute([$selector, $hash, $expires, $user['id']]);
        setcookie('smartedu_remember', $selector . ':' . $validator, [
            'expires' => time() + 60 * 60 * 24 * 30, 'path' => '/',
            'secure'  => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly'=> true, 'samesite' => 'Lax',
        ]);
    }
}

function logout_user(): void {
    if (!empty($_COOKIE['smartedu_remember'])) {
        [$selector] = array_pad(explode(':', $_COOKIE['smartedu_remember'], 2), 2, '');
        if ($selector !== '') {
            foreach(['admins','teachers','students'] as $t)
                db()->prepare("UPDATE {$t} SET remember_selector=NULL,remember_token_hash=NULL,remember_expires_at=NULL WHERE remember_selector=?")
                    ->execute([$selector]);
        }
        setcookie('smartedu_remember', '', time() - 3600, '/');
    }
    if (!empty($_SESSION['user']['session_token'])) {
        $u = $_SESSION['user'];
        $table = $u['table'] ?? 'admins';
        try {
            db()->prepare("UPDATE {$table} SET session_token = NULL WHERE id=? AND session_token=?")->execute([$u['id'], $u['session_token']]);
        } catch (PDOException $e) {}
    }
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

function attempt_remember_login(): void {
    if (is_logged_in() || empty($_COOKIE['smartedu_remember'])) return;
    [$selector, $validator] = array_pad(explode(':', $_COOKIE['smartedu_remember'], 2), 2, '');
    if ($selector === '' || $validator === '') return;
    $tables = ['admins' => 'Academic Admin', 'teachers' => 'Teacher', 'students' => 'Student'];
    $user = $role = $table = null;
    foreach ($tables as $t => $r) {
        $stmt = db()->prepare("SELECT * FROM {$t} WHERE remember_selector=? AND remember_expires_at > UTC_TIMESTAMP() LIMIT 1");
        $stmt->execute([$selector]);
        if ($u = $stmt->fetch()) { $user = $u; $role = $r; $table = $t; break; }
    }
    if (!$user || !hash_equals($user['remember_token_hash'] ?? '', hash('sha256', $validator))) return;
    $user['role'] = $role; $user['table'] = $table;
    login_user($user, false, true);
}

function require_login(): void {
    attempt_remember_login();
    if (!is_logged_in()) { flash_set('error', 'Please log in first.'); redirect('/index.php'); }
}

function require_role(array|string $allowedRoles): void {
    require_login();
    $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $role    = current_user()['role'] ?? '';
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403</title></head><body style="display:grid;place-items:center;min-height:100vh;"><div style="text-align:center;"><h1 style="font-size:64px;color:red;">403</h1><p>Access Denied</p><a href="' . APP_BASE_URL . '/dashboard.php">Dashboard</a></div></body></html>';
        exit;
    }
}

// ─── Gmail SMTP (raw socket, no PHPMailer needed) ─────────────────────────────
function send_smtp_email(string $toAddress, string $toName, string $subject, string $htmlBody): bool|string {
    $host = SMTP_HOST; $port = SMTP_PORT;
    $user = SMTP_USER; $pass = SMTP_PASS; $from = SMTP_USER;
    $fromName = SMTP_FROM_NAME;

    if ($user === '' || $pass === '') return 'SMTP credentials not configured in .env';

    $read = function($s) {
        $r = '';
        while (!feof($s)) { $l = fgets($s, 512); $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
        return $r;
    };

    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) return "Cannot connect to SMTP: {$errstr}";
    stream_set_timeout($socket, 10);

    $read($socket);
    fwrite($socket, "EHLO " . (gethostname() ?: 'localhost') . "\r\n"); $read($socket);
    fwrite($socket, "STARTTLS\r\n"); $read($socket);

    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($socket); return 'TLS negotiation failed';
    }
    fwrite($socket, "EHLO " . (gethostname() ?: 'localhost') . "\r\n"); $read($socket);
    fwrite($socket, "AUTH LOGIN\r\n"); $read($socket);
    fwrite($socket, base64_encode($user) . "\r\n"); $read($socket);
    fwrite($socket, base64_encode($pass) . "\r\n");
    $authResp = $read($socket);
    if (!str_starts_with(trim($authResp), '235')) {
        fclose($socket); return 'SMTP auth failed. Check SMTP_USER / SMTP_PASS in .env';
    }
    fwrite($socket, "MAIL FROM:<{$from}>\r\n"); $read($socket);
    fwrite($socket, "RCPT TO:<{$toAddress}>\r\n");
    $rcptResp = $read($socket);
    if (!str_starts_with(trim($rcptResp), '250')) { fclose($socket); return "RCPT rejected: {$rcptResp}"; }
    fwrite($socket, "DATA\r\n"); $read($socket);

    $b = md5(uniqid('', true));
    $ef = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $et = '=?UTF-8?B?' . base64_encode($toName)   . '?=';
    $es = '=?UTF-8?B?' . base64_encode($subject)   . '?=';

    $msg  = "From: {$ef} <{$from}>\r\nTo: {$et} <{$toAddress}>\r\nSubject: {$es}\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$b}\"\r\n\r\n";
    $msg .= "--{$b}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($htmlBody)) . "\r\n--{$b}--\r\n.\r\n";

    fwrite($socket, $msg);
    $dataResp = $read($socket);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    if (!str_starts_with(trim($dataResp), '250')) return "Message rejected: {$dataResp}";
    return true;
}

function build_password_reset_email(string $name, string $tempPassword, string $loginEmail): string {
    $appName = APP_NAME;
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0f1117;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1117;padding:40px 0;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="background:#1a1d27;border-radius:16px;overflow:hidden;">
<tr><td style="background:#68ba7f;padding:24px 32px;text-align:center;">
  <span style="font-size:28px;font-weight:900;color:#fff;">{$appName}</span>
  <div style="color:rgba(255,255,255,.8);font-size:13px;margin-top:4px;">Academic Platform</div>
</td></tr>
<tr><td style="padding:32px;">
  <h2 style="color:#e2e8f0;margin:0 0 16px;">Password Reset</h2>
  <p style="color:#94a3b8;margin:0 0 20px;">Hello <strong style="color:#e2e8f0;">{$name}</strong>,</p>
  <p style="color:#94a3b8;margin:0 0 24px;">An administrator has reset your {$appName} account. Your new temporary credentials are:</p>
  <div style="background:#0f1117;border:1px solid #2d3748;border-radius:12px;padding:20px;margin-bottom:24px;">
    <div style="margin-bottom:14px;">
      <span style="color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.1em;">Login Email</span><br>
      <span style="color:#68ba7f;font-size:16px;font-weight:700;">{$loginEmail}</span>
    </div>
    <div>
      <span style="color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.1em;">Temporary Password</span><br>
      <span style="color:#e2e8f0;font-size:22px;font-weight:900;letter-spacing:3px;font-family:monospace;">{$tempPassword}</span>
    </div>
  </div>
  <p style="color:#94a3b8;margin:0 0 8px;">⚠️ You will be prompted to change this password on first login.</p>
  <p style="color:#64748b;font-size:12px;margin:0;">If you did not request this, contact your administrator.</p>
</td></tr>
<tr><td style="background:#0f1117;padding:16px 32px;text-align:center;">
  <span style="color:#475569;font-size:12px;">&copy; {$appName} Academic Platform</span>
</td></tr>
</table></td></tr></table></body></html>
HTML;
}
?>
