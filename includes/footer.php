    </main>
</div>

<!-- ═══ LOGOUT CONFIRMATION MODAL ══════════════════════════════ -->
<div id="logout-modal" class="modal-backdrop">
    <div class="modal">
        <div class="modal-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
        </div>
        <h3>Log Out?</h3>
        <p>You'll need to sign in again to access your Herald dashboard.</p>
        <div class="modal-actions">
            <button id="logout-cancel" class="btn secondary">Stay</button>
            <a href="<?= APP_BASE_URL ?>/logout.php" class="btn danger">Log Out</a>
        </div>
    </div>
</div>

<script src="<?= APP_BASE_URL ?>/assets/app.js" defer></script>
</body>
</html>
