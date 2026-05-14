<?php
require_once __DIR__ . '/includes/auth.php';

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
    <meta name="description" content="Sign in to Herald — the unified academic platform for admins, teachers, and students.">
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.add('light-mode');
        }
    </script>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/main.css">
</head>
<body>
<div class="auth-wrapper">
    
    <!-- ══ Animated Background Orbs ══ -->
    <div class="auth-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="orb orb-4"></div>
    </div>
    <div class="auth-grid-overlay"></div>

    <!-- ══ Centered Login Form ══ -->
    <div class="auth-box">

        <div class="auth-topbar" style="margin-bottom: 2rem;">
            <div style="display:flex;align-items:center;gap:1rem;">
                <div class="auth-brand-badge">H</div>
            </div>
            <button id="theme-toggle" class="btn secondary sm auth-theme-toggle" aria-label="Toggle theme">
                <svg id="theme-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </button>
        </div>
        <h1 class="auth-title">Welcome to Herald</h1>
        <p class="auth-sub">Sign in with your email to access your role-based dashboard.</p>

        <!-- Flash -->
        <?php if ($flash): ?>
        <div class="flash <?= esc($flash['type']) ?>" style="margin-bottom:24px;">
            <svg class="flash-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?= esc($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="post" action="">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="input"
                       id="email"
                       type="email"
                       name="email"
                       placeholder="e.g. user@smart.edu.np"
                       autocomplete="username"
                       required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input class="input"
                           id="password"
                           type="password"
                           name="password"
                           placeholder="••••••••"
                           autocomplete="current-password"
                           required>
                    <button type="button" class="pw-eye-btn" data-pw-toggle="password" aria-label="Toggle password">
                        <svg data-icon-eye fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg data-icon-eye-off style="display:none;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:24px;font-size:13px;color:var(--text-muted);">
                <input type="checkbox"
                       name="remember_me"
                       value="1"
                       style="width:15px;height:15px;accent-color:var(--herald-red);cursor:pointer;">
                Remember me for 30 days
            </label>

            <!-- Forgot password -->
            <div style="text-align:center;margin-top:-16px;margin-bottom:20px;">
                <a href="<?= APP_BASE_URL ?>/contact_admin.php"
                   style="font-size:13px;color:var(--text-muted);transition:var(--transition);"
                   onmouseover="this.style.color='var(--herald-red)'"
                   onmouseout="this.style.color='var(--text-muted)'">
                    Forgot password?
                </a>
            </div>

            <button class="btn full lg" type="submit" id="sign-in-btn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                Sign In
            </button>
        </form>

    </div>

</div>
<script src="<?= APP_BASE_URL ?>/assets/app.js" defer></script>
</body>
</html>
