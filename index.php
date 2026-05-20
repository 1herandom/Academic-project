<?php
/**
 * Feature: Login Page with Forgot Password Link
 * Feature Maker: Bipin Guragain
 * Feature Name: Smart Login & Forgot Password Navigation (AP-44 Security Standards)
 * Description: Provides secure login with CSRF protection. Includes a prominent
 *              "Forgot Password?" link redirecting users to the Admin Contact Page
 *              for manual identity-verified password recovery.
 */
require_once __DIR__ . '/includes/auth.php';

// Generate CSRF token for login form
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (is_logged_in()) {
    $role = current_user()['role'];
    if ($role === 'Academic Admin') redirect('/admin/index.php');
    if ($role === 'Teacher')        redirect('/teacher/index.php');
    redirect('/student/index.php');
}

handle_login();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herald — Sign In</title>
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.add('light-mode');
        }
    </script>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/main.css">
    <style>
        .split-container {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow: hidden;
            background: var(--bg-color);
        }
        
        .form-side {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: var(--surface-solid);
            position: relative;
            z-index: 20;
            box-shadow: 10px 0 30px rgba(0,0,0,0.1);
        }
        
        .info-side {
            flex: 1.2;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center; /* Center horizontally */
            text-align: center; /* Center text */
            position: relative;
            overflow: hidden;
            z-index: 10;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
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
            width: 100%;
            max-width: 400px;
            text-align: left; /* Keep text left aligned inside tile */
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

        .login-input {
            width: 100% !important;
            background: transparent !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 16px !important;
            padding: 14px 20px !important;
            color: var(--text-main) !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
            margin-top: 8px;
        }
        
        .support-link {
            display: block;
            margin-top: 12px;
            font-size: 0.95rem;
            color: var(--herald-green);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .support-link:hover {
            color: var(--herald-green);
            transform: translateY(-2px);
            text-shadow: 0 4px 12px rgba(104, 186, 127, 0.3);
        }

        .login-input:focus {
            border-color: var(--herald-green-dark) !important;
            box-shadow: 0 0 0 4px rgba(104, 186, 127, 0.1) !important;
            outline: none !important;
        }

        @media (max-width: 1024px) {
            .split-container { flex-direction: column; height: auto; overflow: auto; }
            .info-side { flex: none; padding: 40px 20px; }
            .form-side { flex: none; padding: 60px 20px; box-shadow: 0 -10px 30px rgba(0,0,0,0.1); order: 2; }
        }
    </style>
</head>
<body>

<div class="split-container">
    
    <!-- ══ LEFT SIDE: THE FORM ══ -->
    <div class="form-side">
        <div class="login-card">
            <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                <div class="auth-brand-badge" style="background: var(--herald-green-dark); color: white; width: 44px; height: 44px; font-size: 1.2rem;">H</div>
                <button id="theme-toggle" class="btn secondary sm" style="border-radius: 12px;">
                    <svg id="theme-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>
            </div>

            <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 8px; color: var(--text-main);">Login</h1>
            <p class="muted" style="margin-bottom: 32px;">Enter your account details to access your portal.</p>

            <?php if ($flash): ?>
            <div class="flash <?= esc($flash['type']) ?>" style="margin-bottom: 24px; border-radius: 16px;">
                <svg class="flash-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= esc($flash['message']) ?>
            </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="email" style="text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 700; color: var(--text-faint);">Username or Email</label>
                    <input class="login-input" id="email" type="email" name="email" placeholder="user@smart.edu.np" required>
                </div>

                <div class="form-group">
                    <label for="password" style="text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 700; color: var(--text-faint);">Password</label>
                    <div style="position: relative;">
                        <input class="login-input" id="password" type="password" name="password" placeholder="••••••••" required>
                        <button type="button" class="pw-eye-btn" data-pw-toggle="password" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-faint); cursor: pointer;">
                            <svg data-icon-eye width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg data-icon-eye-off style="display:none;" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <a href="<?= APP_BASE_URL ?>/contact_admin.php" class="support-link" id="forgot-password-link">
                    🔑 Forgot Password? Contact Admin
                </a>

                <button class="btn full lg mt-8" type="submit" style="border-radius: 16px; background: var(--herald-green-dark); padding: 16px;">
                    Login
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="margin-left: 8px;"><path d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>
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

        <div style="max-width: 500px; position: relative; z-index: 50; display: flex; flex-direction: column; align-items: center;">
            <div class="auth-brand-badge" style="width: 60px; height: 60px; font-size: 2rem; background: var(--herald-green-dark); color: white;">H</div>
            <h1 style="font-size: 3rem; font-weight: 800; margin: 24px 0 16px; line-height: 1.1; color: var(--text-main);">Herald Academic</h1>
            <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 40px; line-height: 1.6;">
                Access your courses, grades, and resources. Join thousands of students and educators in the Herald academic ecosystem.
            </p>

            <div class="contact-tile">
                <div class="tile-icon" style="background: rgba(125, 255, 178, 0.1); color: var(--herald-green);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div>
                    <div class="small font-bold" style="text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-faint);">Digital Library</div>
                    <div style="font-weight: 600; font-size: 1.1rem;">Infinite Resources</div>
                </div>
            </div>

            <div class="contact-tile">
                <div class="tile-icon" style="background: rgba(56, 189, 248, 0.1); color: var(--herald-blue);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <div class="small font-bold" style="text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-faint);">Secure Portal</div>
                    <div style="font-weight: 600; font-size: 1.1rem;">End-to-End Privacy</div>
                </div>
            </div>

            <div class="contact-tile">
                <div class="tile-icon" style="background: rgba(228, 145, 166, 0.1); color: var(--herald-red);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <div class="small font-bold" style="text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-faint);">Fast Performance</div>
                    <div style="font-weight: 600; font-size: 1.1rem;">Optimized Experience</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= APP_BASE_URL ?>/assets/app.js" defer></script>
</body>
</html>
