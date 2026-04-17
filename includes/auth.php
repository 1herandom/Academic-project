<?php
// Include configuration file (database connection, constants)
require_once __DIR__ . '/../config.php';

/*
|--------------------------------------------------------------------------
| Feature 5 | Suprim: Credential validation and secure session security
| Feature 1 | Bipin Guragain: Post-login redirect to role dashboards
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| LOGIN HANDLER FUNCTION
|--------------------------------------------------------------------------
*/

// Function to process login requests
function handle_login(): void {

    // Only process POST requests (form submission)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    // Retrieve and sanitize user inputs
    $login    = trim($_POST['login'] ?? '');        // Email or institutional ID
    $password = (string)($_POST['password'] ?? '');// Password input
    $remember = !empty($_POST['remember_me']);     // Remember me checkbox

    // Validate required fields
    if ($login === '' || $password === '') {
        flash_set('error', 'Please enter your email (or Institutional ID) and password.');
        redirect('/index.php');
    }

    // Initialize database connection
    $pdo = db();

    // Determine login type (email or institutional ID)
    if (str_contains($login, '@')) {
        // Login using email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status='active' LIMIT 1");
    } else {
        // Login using institutional ID
        $stmt = $pdo->prepare("SELECT * FROM users WHERE institutional_id = ? AND status='active' LIMIT 1");
    }

    // Execute query
    $stmt->execute([$login]);

    // Fetch user record
    $user = $stmt->fetch();

    // Verify user exists and password matches (secure hash check)
    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash_set('error', 'Invalid credentials. Please check your email and password.');
        redirect('/index.php');
    }

    // Create secure session and log user in
    login_user($user, $remember);

    // Check if user is using a temporary password
    if ((int)$user['temp_password'] === 1) {
        flash_set('info', 'Temporary password detected. Please change your password.');
    }

    // Redirect user based on role (role-based access control)
    if ($user['role'] === 'Academic Admin') redirect('/admin/index.php');
    if ($user['role'] === 'Teacher') redirect('/teacher/index.php');

    // Default redirect (Student)
    redirect('/student/index.php');
}

/*
|--------------------------------------------------------------------------
| PASSWORD UPDATE FUNCTION
|--------------------------------------------------------------------------
*/

// Function to update user password securely
function update_password(int $userId, string $newPassword): bool {

    // Enforce minimum password length
    if (strlen($newPassword) < 8) return false;

    // Prepare SQL statement to update password
    $stmt = db()->prepare("UPDATE users SET password_hash = ?, temp_password = 0 WHERE id = ?");

    // Execute update with hashed password (BCRYPT encryption)
    return $stmt->execute([
        password_hash($newPassword, PASSWORD_BCRYPT), // Secure hashing
        $userId
    ]);
}

/*
|--------------------------------------------------------------------------
| ACTIVE USER VALIDATION FUNCTION
|--------------------------------------------------------------------------
*/

// Ensure user is logged in and account is active
function ensure_user_active(): void {

    // Check if user is logged in
    require_login();

    // Get current user data
    $user = current_user();

    // If account is not active (e.g., archived)
    if (($user['status'] ?? 'active') !== 'active') {

        // Log user out immediately
        logout_user();

        // Show error message
        flash_set('error', 'Your account is archived.');

        // Redirect to login page
        redirect('/index.php');
    }
}
?>