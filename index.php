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
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/style.css">
</head>
<body>
<div class="auth-split">

    <!-- ══ LEFT: Login Form ══════════════════════════════════ -->
    <div class="auth-left">
        <div class="auth-box">

            <div class="auth-brand-badge">H</div>
            <h1 class="auth-title">Welcome to Herald</h1>
            <p class="auth-sub">Sign in with your college email or Institutional ID to access your dashboard.</p>

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

            <form method="post" action="" id="login-form" autocomplete="on">

                <div class="form-group" style="position:relative;">
                    <label for="login_field">Email or Institutional ID</label>
                    <input class="input"
                           id="login_field"
                           type="text"
                           name="login"
                           placeholder="e.g. john.doe1234@herald.edu.np"
                           autocomplete="username"
                           required>
                    <!-- Recent logins dropdown -->
                    <div id="recent-logins-dropdown" style="display:none;"></div>
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

                <button class="btn full lg" type="submit" id="sign-in-btn">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Sign In
                </button>
            </form>

            <!-- Role indicators -->
            <div class="auth-roles">
                <span style="font-size:12px;color:var(--text-faint);font-weight:600;margin-right:4px;align-self:center;">Access:</span>
                <span class="pill red">Admin</span>
                <span class="pill amber">Teacher</span>
                <span class="pill green">Student</span>
            </div>
            <p class="small mt-8">Your role is auto-detected. Permissions are strictly enforced.</p>

        </div>
    </div>

    <!-- ══ RIGHT: Animated Background ═══════════════════════ -->
    <div class="auth-right">
        <!-- Animated colour orbs -->
        <div class="auth-orbs">
            <div class="orb orb-1"></div>
            <div class="orb orb-2"></div>
            <div class="orb orb-3"></div>
            <div class="orb orb-4"></div>
        </div>
        <!-- Subtle grid lines -->
        <div class="auth-grid-overlay"></div>

        <!-- Quote card -->
        <div class="auth-quote">
            <h3>Empowering Modern Education</h3>
            <p>Herald unifies administrators, teachers, and students in one seamless academic platform — built for clarity, speed, and purpose.</p>
        </div>
    </div>

</div>
<style>
#recent-logins-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--surface-raised, #1e1e2e);
    border: 1px solid var(--border, rgba(255,255,255,0.1));
    border-radius: 10px;
    margin-top: 4px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.35);
    z-index: 100;
    overflow: hidden;
}
.recent-login-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-muted, #aaa);
    transition: background 0.15s;
}
.recent-login-item:hover {
    background: rgba(255,255,255,0.06);
    color: var(--text, #fff);
}
.recent-login-item svg { flex-shrink: 0; opacity: 0.6; }
.recent-login-item span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.recent-login-clear {
    display: block;
    width: 100%;
    padding: 8px 14px;
    font-size: 11px;
    color: var(--herald-red, #e05);
    background: none;
    border: none;
    border-top: 1px solid var(--border, rgba(255,255,255,0.08));
    cursor: pointer;
    text-align: left;
    transition: background 0.15s;
}
.recent-login-clear:hover { background: rgba(255,0,0,0.06); }
</style>
<script src="<?= APP_BASE_URL ?>/assets/app.js" defer></script>
<script>
(function() {
    const STORAGE_KEY = 'herald_recent_logins';
    const MAX = 5;

    function getRecent() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
        catch { return []; }
    }

    function saveRecent(logins) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(logins.slice(0, MAX)));
    }

    function addRecent(val) {
        if (!val) return;
        let list = getRecent().filter(x => x !== val);
        list.unshift(val);
        saveRecent(list);
    }

    const input = document.getElementById('login_field');
    const dropdown = document.getElementById('recent-logins-dropdown');
    if (!input || !dropdown) return;

    function renderDropdown() {
        const list = getRecent();
        if (list.length === 0) { dropdown.style.display = 'none'; return; }
        dropdown.innerHTML = '';
        list.forEach(item => {
            const div = document.createElement('div');
            div.className = 'recent-login-item';
            div.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>${item}</span>`;
            div.addEventListener('mousedown', (e) => {
                e.preventDefault();
                input.value = item;
                dropdown.style.display = 'none';
                input.focus();
            });
            dropdown.appendChild(div);
        });
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'recent-login-clear';
        clearBtn.textContent = '✕ Clear recent logins';
        clearBtn.addEventListener('mousedown', (e) => {
            e.preventDefault();
            localStorage.removeItem(STORAGE_KEY);
            dropdown.style.display = 'none';
        });
        dropdown.appendChild(clearBtn);
        dropdown.style.display = 'block';
    }

    input.addEventListener('focus', () => {
        if (getRecent().length > 0 && input.value === '') renderDropdown();
    });
    input.addEventListener('blur', () => {
        setTimeout(() => { dropdown.style.display = 'none'; }, 150);
    });
    input.addEventListener('input', () => {
        if (input.value !== '') { dropdown.style.display = 'none'; }
        else { renderDropdown(); }
    });

    // Save login value on form submit
    const form = document.getElementById('login-form');
    if (form) {
        form.addEventListener('submit', () => {
            const val = input.value.trim();
            if (val) addRecent(val);
        });
    }
})();
</script>
</body>
</html>
