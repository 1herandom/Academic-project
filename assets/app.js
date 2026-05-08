/* Herald — app.js */

/* ── Sidebar Toggle ─────────────────────────────────────────── */
function toggleSidebar() {
  document.body.classList.toggle('sidebar-open');
}

/* ── Logout Modal ───────────────────────────────────────────── */
function openLogoutModal() {
  const backdrop = document.getElementById('logout-modal');
  if (backdrop) backdrop.classList.add('open');
}

function openNewChatModal() {
  const backdrop = document.getElementById('new-chat-modal');
  if (backdrop) {
    backdrop.style.display = 'flex';
    backdrop.classList.add('open');
  }
}

function closeNewChatModal() {
  const backdrop = document.getElementById('new-chat-modal');
  if (backdrop) {
    backdrop.style.display = 'none';
    backdrop.classList.remove('open');
  }
}

/* ── Flash Auto-dismiss ─────────────────────────────────────── */
function initFlashDismiss() {
  const flashes = document.querySelectorAll('.flash');
  flashes.forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 520);
    }, 5200);
  });
}

/* ── Attendance Counters ────────────────────────────────────── */
function bindAttendanceCounters() {
  const container = document.querySelector('[data-attendance-grid]');
  if (!container) return;

  const updateCounts = () => {
    const presentChecked = container.querySelectorAll('input[type="checkbox"][data-status="present"]:checked').length;
    const absentChecked  = container.querySelectorAll('input[type="checkbox"][data-status="absent"]:checked').length;
    const presentEl = document.querySelector('[data-present-count]');
    const absentEl  = document.querySelector('[data-absent-count]');
    if (presentEl) presentEl.textContent = presentChecked;
    if (absentEl)  absentEl.textContent  = absentChecked;
  };

  container.addEventListener('change', (event) => {
    if (event.target.matches('input[type="checkbox"]')) {
      const row = event.target.closest('tr');
      if (row && event.target.dataset.status === 'present' && event.target.checked) {
        const absent = row.querySelector('input[data-status="absent"]');
        if (absent) absent.checked = false;
      }
      if (row && event.target.dataset.status === 'absent' && event.target.checked) {
        const present = row.querySelector('input[data-status="present"]');
        if (present) present.checked = false;
      }
      updateCounts();
    }
  });

  document.querySelector('[data-mark-all-present]')?.addEventListener('click', () => {
    container.querySelectorAll('input[data-status="present"]').forEach(cb => cb.checked = true);
    container.querySelectorAll('input[data-status="absent"]').forEach(cb  => cb.checked = false);
    updateCounts();
  });

  updateCounts();
}

/* ── Deadline Lockdown ──────────────────────────────────────── */
function deadlineLockdown() {
  const submitBtn  = document.querySelector('[data-submission-submit]');
  const deadlineEl = document.querySelector('[data-deadline]');
  if (!submitBtn || !deadlineEl) return;

  const deadline = new Date(deadlineEl.dataset.deadline).getTime();

  const timer = () => {
    if (Date.now() >= deadline + 60000) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submission Closed';
      const note = document.querySelector('[data-deadline-note]');
      if (note) note.textContent = 'The deadline has passed. Submission is closed.';
      clearInterval(intervalId);
    }
  };

  const intervalId = setInterval(timer, 1000);
  timer();
}

/* ── Password Eye Toggle ────────────────────────────────────── */
function bindPasswordToggles() {
  document.querySelectorAll('[data-pw-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.dataset.pwToggle;
      const input = document.getElementById(targetId);
      if (!input) return;
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      // swap icons
      const eyeOpen   = btn.querySelector('[data-icon-eye]');
      const eyeClosed = btn.querySelector('[data-icon-eye-off]');
      if (eyeOpen)   eyeOpen.style.display   = isText ? 'block' : 'none';
      if (eyeClosed) eyeClosed.style.display = isText ? 'none'  : 'block';
    });
  });
}

/* ── Password Strength ──────────────────────────────────────── */
function bindPasswordStrength() {
  const input     = document.querySelector('[data-pw-strength]');
  const fill      = document.querySelector('.pw-strength-fill');
  const label     = document.querySelector('.pw-strength-label');
  if (!input || !fill || !label) return;

  const levels = [
    { w: '0%',   color: 'transparent',         text: '' },
    { w: '25%',  color: 'var(--herald-red)',    text: 'Weak' },
    { w: '50%',  color: 'var(--herald-amber)',  text: 'Fair' },
    { w: '75%',  color: 'var(--herald-gold)',   text: 'Good' },
    { w: '100%', color: 'var(--herald-green)',  text: 'Strong' },
  ];

  function score(pw) {
    if (!pw) return 0;
    let s = 0;
    if (pw.length >= 8)  s++;
    if (pw.length >= 12) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return Math.min(4, Math.floor(s * 4 / 5) + (s > 0 ? 1 : 0));
  }

  input.addEventListener('input', () => {
    const s = score(input.value);
    const lvl = levels[s] ?? levels[0];
    fill.style.width = lvl.w;
    fill.style.backgroundColor = lvl.color;
    label.textContent = lvl.text;
  });
}

/* ── Profile Photo Preview ──────────────────────────────────── */
function bindPhotoPreview() {
  const fileInput = document.getElementById('photo-file-input');
  const preview   = document.getElementById('photo-preview');
  if (!fileInput || !preview) return;

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      alert('Photo must be under 2 MB.');
      fileInput.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = e => {
      // If there's an <img> use it, otherwise show it
      let img = preview.querySelector('img.profile-photo');
      if (!img) {
        // Replace initials div with img
        preview.innerHTML = '';
        img = document.createElement('img');
        img.className = 'profile-photo';
        preview.appendChild(img);
        // Also update topbar avatar
        const topbarAvatar = document.getElementById('topbar-avatar-el');
        if (topbarAvatar && topbarAvatar.tagName === 'DIV') {
          const newImg = document.createElement('img');
          newImg.src = e.target.result;
          newImg.className = 'topbar-avatar';
          topbarAvatar.replaceWith(newImg);
        }
      }
      img.src = e.target.result;
      // Also update topbar avatar img if already an img
      const topbarImg = document.querySelector('#topbar-avatar-el img, img#topbar-avatar-el');
      if (topbarImg) topbarImg.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

/* ── Theme Toggle ───────────────────────────────────────────── */
function bindThemeToggle() {
  const sunPath = "M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z";
  const moonPath = "M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z";

  const updateIcon = () => {
    const themeIcon = document.getElementById('theme-icon');
    if (!themeIcon) return;
    const isLight = document.documentElement.classList.contains('light-mode');
    themeIcon.querySelector('path').setAttribute('d', isLight ? moonPath : sunPath);
  };

  updateIcon();

  document.addEventListener('click', e => {
    const btn = e.target.closest('#theme-toggle');
    if (btn) {
      const isLight = document.documentElement.classList.toggle('light-mode');
      localStorage.setItem('theme', isLight ? 'light' : 'dark');
      updateIcon();
      
      // Special handling for Chart.js if present
      if (typeof Chart !== 'undefined') {
        location.reload(); // Simplest way to redraw all charts with new theme colors
      }
    }
  });
}

/* ── Init ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  bindThemeToggle();
  // Sidebar
  // Sidebar Toggle - Using delegation for better reliability
  document.addEventListener('click', e => {
    const trigger = e.target.closest('[data-toggle-sidebar]');
    if (trigger) {
      toggleSidebar();
    }
  });

  // Logout modal
  document.querySelectorAll('[data-logout-trigger]').forEach(el =>
    el.addEventListener('click', e => { e.preventDefault(); openLogoutModal(); })
  );

  const cancelBtn = document.getElementById('logout-cancel');
  if (cancelBtn) cancelBtn.addEventListener('click', closeLogoutModal);

  // Close modal on backdrop click
  const backdrop = document.getElementById('logout-modal');
  if (backdrop) {
    backdrop.addEventListener('click', e => {
      if (e.target === backdrop) closeLogoutModal();
    });
  }

  // Escape closes modal
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLogoutModal();
  });

  // All other inits
  initFlashDismiss();
  bindAttendanceCounters();
  deadlineLockdown();
  bindPasswordToggles();
  bindPasswordStrength();
  bindPhotoPreview();
});
