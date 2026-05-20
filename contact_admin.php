<?php
/**
 * Feature: Forgot Password / Contact Admin Page
 * Feature Maker: Bipin Guragain
 * Feature Name: Password Reset Request via Admin Contact (AP-44 Security Standards)
 * Description: Allows students and faculty to submit identity-verified password
 *              reset requests that are logged in the password_requests table.
 *              All inputs are sanitized against XSS and SQL Injection.
 */
require_once __DIR__ . '/config.php';

// ── Ensure password_requests table exists (auto-migration) ────────────────
try {
    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_requests (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            full_name    VARCHAR(150) NOT NULL,
            email        VARCHAR(180) NOT NULL,
            student_id   VARCHAR(80)  NOT NULL,
            request_type ENUM('password_reset','general') NOT NULL DEFAULT 'password_reset',
            status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            temp_password VARCHAR(50) NULL,
            created_at   DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
} catch (Exception $ignored) {}

// ── CSRF token generation ─────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token_contact'])) {
    $_SESSION['csrf_token_contact'] = bin2hex(random_bytes(32));
}

$flash = flash_get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF validation ───────────────────────────────────────────────────
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token_contact'] ?? '', $submittedToken)) {
        flash_set('error', 'Invalid security token. Please try again.');
        header('Location: ' . APP_BASE_URL . '/contact_admin.php');
        exit;
    }

    // ── Sanitize inputs (XSS + SQL Injection prevention via PDO) ─────────
    $name       = htmlspecialchars(strip_tags(trim($_POST['full_name']   ?? '')), ENT_QUOTES, 'UTF-8');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $studentId  = htmlspecialchars(strip_tags(trim($_POST['student_id'] ?? '')), ENT_QUOTES, 'UTF-8');
    $message    = htmlspecialchars(strip_tags(trim($_POST['message']    ?? '')), ENT_QUOTES, 'UTF-8');
    $reqType    = ($_POST['request_type'] ?? '') === 'password_reset' ? 'password_reset' : 'general';

    // ── Validate herald.edu.np domain ─────────────────────────────────────
    $emailValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    $domainOk   = str_ends_with(strtolower($email), '@herald.edu.np');

    if ($name === '' || $email === '' || $studentId === '') {
        flash_set('error', 'Please fill in all required fields before submitting.');
    } elseif (!$emailValid || !$domainOk) {
        flash_set('error', 'Please enter a valid institutional email address ending in @herald.edu.np.');
    } else {
        try {
            $pdo = db();

            // Also insert into legacy support_requests for backward compatibility
            $legacy = $pdo->prepare(
                "INSERT INTO support_requests (full_name, email, message, status) VALUES (?, ?, ?, 'pending')"
            );
            $legacy->execute([$name, $email,
                ($reqType === 'password_reset' ? '[PASSWORD RESET] ' : '') .
                "ID: {$studentId}" . ($message !== '' ? " | {$message}" : '')
            ]);

            // Insert into dedicated password_requests table
            $stmt = $pdo->prepare(
                "INSERT INTO password_requests (full_name, email, student_id, request_type, status) VALUES (?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([$name, $email, $studentId, $reqType]);

            // Regenerate CSRF token after successful submission
            $_SESSION['csrf_token_contact'] = bin2hex(random_bytes(32));

            flash_set('success',
                'Your request has been sent to the Admin. Please wait for manual verification.');
        } catch (Exception $e) {
            flash_set('error', 'Failed to submit request. Please try again later.');
        }
    }
    header('Location: ' . APP_BASE_URL . '/contact_admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Administrator — Herald</title>
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.add('light-mode');
        }
    </script>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/main.css">
    <style>
        .split-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden;
            background: var(--bg-color);
        }
        
        .info-side {
            flex: 1.2;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            z-index: 10;
        }
        
        .form-side {
            flex: 1;
            padding: 60px;
            background: var(--surface-solid);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 20;
            box-shadow: 10px 0 30px rgba(0,0,0,0.1);
        }
        
        .contact-tile {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            margin-bottom: 16px;
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }
        
        .contact-tile:hover {
            background: rgba(255,255,255,0.06);
            transform: translateX(10px);
            border-color: var(--herald-green);
        }
        
        .tile-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        @media (max-width: 1024px) {
            .split-container { flex-direction: column; }
            .info-side { flex: none; padding: 40px 20px; }
            .form-side { flex: none; padding: 40px 20px; box-shadow: 0 -10px 30px rgba(0,0,0,0.1); }
        }
    </style>
</head>
<body>

<div class="split-container">
    
    <!-- ══ LEFT SIDE: THE FORM ══ -->
    <div class="form-side">
        <div style="width: 100%; max-width: 440px;">
            <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                <a href="<?= APP_BASE_URL ?>/index.php" class="btn secondary sm" style="border-radius: 12px; gap: 8px;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                    Back
                </a>
                <button id="theme-toggle" class="btn secondary sm" style="border-radius: 12px;">
                    <svg id="theme-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>
            </div>

            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 8px; color: var(--text-main);">Contact Administrator</h2>
            <p class="muted" style="margin-bottom: 32px;">Have a question or need technical support? Send us a message.</p>

            <?php if ($flash): ?>
            <div class="flash <?= esc($flash['type']) ?>" style="margin-bottom: 24px; border-radius: 16px;">
                <svg class="flash-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= esc($flash['message']) ?>
            </div>
            <?php endif; ?>

            <form method="post" action="" id="contactForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token_contact'] ?>">

                <div class="form-group">
                    <label for="request_type" style="text-transform:uppercase;font-size:.75rem;letter-spacing:.05em;font-weight:700;color:var(--text-faint);">Request Type</label>
                    <select class="input" id="request_type" name="request_type" style="margin-top:8px;" required>
                        <option value="password_reset">🔑 Forgot Password / Reset Request</option>
                        <option value="general">💬 General Support</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="full_name" style="text-transform:uppercase;font-size:.75rem;letter-spacing:.05em;font-weight:700;color:var(--text-faint);">Full Name</label>
                    <input class="input" id="full_name" type="text" name="full_name"
                           placeholder="e.g. Sita Sharma"
                           maxlength="150"
                           style="margin-top:8px;"
                           required>
                </div>

                <div class="form-group">
                    <label for="email" style="text-transform:uppercase;font-size:.75rem;letter-spacing:.05em;font-weight:700;color:var(--text-faint);">Institutional Email <span style="color:var(--herald-red);">(@herald.edu.np)</span></label>
                    <input class="input" id="email" type="email" name="email"
                           placeholder="user@herald.edu.np"
                           pattern=".+@herald\.edu\.np$"
                           maxlength="180"
                           style="margin-top:8px;"
                           required>
                    <p class="small muted" style="margin-top:4px;">Must be your official herald.edu.np address.</p>
                </div>

                <div class="form-group">
                    <label for="student_id" style="text-transform:uppercase;font-size:.75rem;letter-spacing:.05em;font-weight:700;color:var(--text-faint);">Student / Staff ID</label>
                    <input class="input" id="student_id" type="text" name="student_id"
                           placeholder="e.g. HER-2024-001"
                           maxlength="80"
                           style="margin-top:8px;"
                           required>
                    <p class="small muted" style="margin-top:4px;">Used to verify your identity with the Admin.</p>
                </div>

                <div class="form-group">
                    <label for="message" style="text-transform:uppercase;font-size:.75rem;letter-spacing:.05em;font-weight:700;color:var(--text-faint);">Additional Details <span class="muted" style="font-weight:400;">(Optional)</span></label>
                    <textarea class="input" id="message" name="message" rows="3"
                              placeholder="Describe your issue (optional)"
                              maxlength="1000"
                              style="resize:none;margin-top:8px;"></textarea>
                </div>

                <button class="btn full lg mt-4" type="submit" id="submitBtn" style="border-radius: 16px; background: var(--herald-green-dark); padding:16px;">
                    Send Request to Admin
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>

            <script>
            // Client-side domain validation for UX feedback
            document.getElementById('email').addEventListener('blur', function(){
                const val = this.value.trim().toLowerCase();
                const note = this.nextElementSibling;
                if (val && !val.endsWith('@herald.edu.np')) {
                    this.style.borderColor = 'var(--herald-red)';
                    note.textContent = '⚠️ Email must end with @herald.edu.np';
                    note.style.color = 'var(--herald-red)';
                } else {
                    this.style.borderColor = '';
                    note.textContent = 'Must be your official herald.edu.np address.';
                    note.style.color = '';
                }
            });
            document.getElementById('contactForm').addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim().toLowerCase();
                if (!email.endsWith('@herald.edu.np')) {
                    e.preventDefault();
                    document.getElementById('email').focus();
                    alert('Please use your official @herald.edu.np institutional email address.');
                    return false;
                }
                document.getElementById('submitBtn').disabled = true;
                document.getElementById('submitBtn').textContent = 'Sending…';
            });
            </script>

            <p class="small mt-8" style="text-align: left; opacity: 0.6;">
                Response typically within 1 business day.
            </p>
        </div>
    </div>

    <!-- ══ RIGHT SIDE: BRAND & INFO ══ -->
    <div class="info-side">
        <div class="auth-orbs" style="opacity: 0.4;">
            <div class="orb orb-1"></div>
            <div class="orb orb-2"></div>
            <div class="orb orb-3"></div>
            <div class="orb orb-4"></div>
        </div>
        <div class="auth-grid-overlay"></div>

        <div style="max-width: 500px; position: relative; z-index: 50;">
            <div class="auth-brand-badge" style="width: 60px; height: 60px; font-size: 2rem;">H</div>
            <h1 style="font-size: 3rem; font-weight: 800; margin: 24px 0 16px; line-height: 1.1;">Herald Support</h1>
            <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 40px; line-height: 1.6;">
                Have a question about your account, a technical issue, or need a password reset? Our administrators are here to help you navigate the Herald platform.
            </p>

            <div class="contact-tile">
                <div class="tile-icon" style="background: rgba(228, 145, 166, 0.1); color: var(--herald-red);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <div class="small font-bold" style="text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-faint);">Email Support</div>
                    <div style="font-weight: 600; font-size: 1.1rem;">admin@smart.edu.np</div>
                </div>
            </div>

            <div class="contact-tile">
                <div class="tile-icon" style="background: rgba(56, 189, 248, 0.1); color: var(--herald-blue);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <div class="small font-bold" style="text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-faint);">Admin Office</div>
                    <div style="font-weight: 600; font-size: 1.1rem;">Room 101, Academic Block</div>
                </div>
            </div>

            <div class="contact-tile">
                <div class="tile-icon" style="background: rgba(52, 211, 153, 0.1); color: var(--herald-green);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="small font-bold" style="text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-faint);">Office Hours</div>
                    <div style="font-weight: 600; font-size: 1.1rem;">Sun – Fri | 9 AM – 5 PM</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= APP_BASE_URL ?>/assets/app.js" defer></script>
</body>
</html>
