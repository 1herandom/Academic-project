    </main> <!-- End of main content area -->

</div> <!-- End of layout container -->

<!-- ═══ LOGOUT CONFIRMATION MODAL ══════════════════════════════ -->

<!-- Modal backdrop (dark overlay behind popup) -->
<div id="logout-modal" class="modal-backdrop">

    <!-- Modal box -->
    <div class="modal">

        <!-- Logout icon -->
        <div class="modal-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
        </div>

        <!-- Modal title -->
        <h3>Log Out?</h3>

        <!-- Description message -->
        <p>You'll need to sign in again to access your Herald dashboard.</p>

        <!-- Action buttons -->
        <div class="modal-actions">

            <!-- Cancel logout button -->
            <button id="logout-cancel" class="btn secondary">Stay</button>

            <!-- Confirm logout (redirects to logout script) -->
            <a href="<?= APP_BASE_URL ?>/logout.php" class="btn danger">Log Out</a>
        </div>
    </div>
</div>

<!-- Load main JavaScript file (handles UI interactions like modal, sidebar, etc.) -->
<script src="<?= APP_BASE_URL ?>/assets/app.js" defer></script>

</body>
</html>