<?php
require_once __DIR__ . '/includes/header.php';
$user      = current_user();
$firstName = esc($user['first_name'] ?? 'there');
?>

<main class="main-content">
<div class="container" style="max-width:900px;">

    <!-- Page Header -->
    <div class="page-header-layout" style="margin-bottom:0;">
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--herald-red),#a855f7);display:grid;place-items:center;flex-shrink:0;box-shadow:0 0 18px rgba(239,68,68,0.35);">
                <svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24" style="width:22px;height:22px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                </svg>
            </div>
            <div>
                <h1 style="font-size:1.4rem;margin:0;">Herald AI Assistant</h1>
                <p style="margin:0;font-size:13px;color:var(--text-muted);">Your academic companion — platform help &amp; learning support</p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span id="status-badge" class="pill green" style="font-size:11px;display:flex;align-items:center;gap:5px;">
                <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                Online
            </span>
            <button id="clear-btn" class="btn secondary sm" title="Clear chat history" style="display:flex;align-items:center;gap:6px;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Clear
            </button>
        </div>
    </div>

    <!-- Chat Card -->
    <div class="chat-container-card">

        <!-- Messages area -->
        <div id="chat-messages" style="flex:1;overflow-y:auto;padding:24px;display:flex;flex-direction:column;scroll-behavior:smooth;">

            <!-- Welcome message -->
            <div class="chat-row assistant" id="welcome-msg">
                <div class="chat-avatar ai-avatar">
                    <svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                    </svg>
                </div>
                <div style="max-width:75%;">
                    <div class="bubble assistant-bubble">
                        <p style="margin:0 0 8px;font-weight:600;">Hello, <?= $firstName ?>! 👋</p>
                        <p style="margin:0 0 8px;">I'm <strong>Herald Assistant</strong>, your academic AI companion. I can help you with:</p>
                        <ul style="margin:0 0 8px;padding-left:18px;line-height:1.9;">
                            <li>📚 Navigating the Herald platform</li>
                            <li>📝 Assignment &amp; submission questions</li>
                            <li>🧪 Quiz preparation &amp; course concepts</li>
                            <li>📅 Attendance &amp; academic materials</li>
                            <li>💡 Study tips &amp; learning strategies</li>
                        </ul>
                        <p style="margin:0;color:var(--text-muted);font-size:13px;">What can I help you with today?</p>
                    </div>
                    <div class="bubble-time">Herald AI • Just now</div>
                </div>
            </div>

        </div>

        <!-- Typing indicator (hidden by default) -->
        <div id="typing-row" class="chat-row assistant" style="display:none;padding:0 24px 12px;">
            <div class="chat-avatar ai-avatar">
                <svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                </svg>
            </div>
            <div class="bubble assistant-bubble typing-bubble">
                <span class="dot"></span><span class="dot"></span><span class="dot"></span>
            </div>
        </div>

        <!-- Divider -->
        <div style="height:1px;background:var(--border-color);opacity:0.4;flex-shrink:0;"></div>

        <!-- Suggested prompts -->
        <div id="suggestions" style="padding:16px 24px;display:flex;gap:10px;flex-wrap:wrap;flex-shrink:0;background:var(--surface-color);">
            <span style="font-size:11px;color:var(--text-faint);align-self:center;font-weight:600;margin-right:4px;">Try:</span>
            <button class="suggest-chip" data-msg="How do I submit an assignment?">Submit assignment</button>
            <button class="suggest-chip" data-msg="Where can I see my quiz results?">Quiz results</button>
            <button class="suggest-chip" data-msg="How do I check my attendance?">Check attendance</button>
            <button class="suggest-chip" data-msg="Give me some study tips for exams.">Study tips</button>
        </div>

        <!-- Input area -->
        <div class="chat-input-wrapper">
            <div style="flex:1;position:relative;">
                <textarea
                    id="chat-input"
                    class="chat-input-area"
                    placeholder="Ask Herald AI anything about your courses or the platform…"
                    rows="1"
                ></textarea>
                <div style="position:absolute;right:18px;bottom:14px;font-size:11px;color:var(--text-faint);" id="char-count"></div>
            </div>
            <button id="send-button"
                style="width:50px;height:50px;border-radius:50%;border:none;cursor:pointer;background:linear-gradient(135deg,var(--herald-red),#c02020);color:white;display:grid;place-items:center;flex-shrink:0;box-shadow:0 4px 14px rgba(239,68,68,0.4);transition:transform 0.2s ease, box-shadow 0.2s ease;"
                aria-label="Send message">
                <svg id="send-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:20px;height:20px;margin-left:2px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                <!-- Spinner (hidden) -->
                <svg id="send-spinner" style="display:none;animation:spin 0.8s linear infinite;" fill="none" viewBox="0 0 24 24" style="width:20px;height:20px;">
                    <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/>
                    <path d="M12 2a10 10 0 0110 10" stroke="white" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

    </div>

    <p style="text-align:center;font-size:12px;color:var(--text-faint);margin-top:10px;">
        Herald AI is scoped to this platform and academic topics only. For account issues, contact your
        <a href="<?= APP_BASE_URL ?>/contact_admin.php" style="color:var(--herald-red);">system administrator</a>.
    </p>

</div>
</main>

<script>
(function () {
    const apiUrl      = '<?= APP_BASE_URL ?>/chatbot_api.php';
    const initials    = '<?= esc(user_initials($user)) ?>';
    const firstName   = '<?= $firstName ?>';

    const messagesEl  = document.getElementById('chat-messages');
    const inputEl     = document.getElementById('chat-input');
    const sendBtn     = document.getElementById('send-button');
    const sendIcon    = document.getElementById('send-icon');
    const sendSpinner = document.getElementById('send-spinner');
    const typingRow   = document.getElementById('typing-row');
    const clearBtn    = document.getElementById('clear-btn');
    const suggestions = document.getElementById('suggestions');

    let messages  = [];
    let isSending = false;

    /* ── Auto-grow textarea ── */
    function autoGrow() {
        inputEl.style.height = 'auto';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 140) + 'px';
    }
    inputEl.addEventListener('input', autoGrow);

    /* ── Scroll to bottom ── */
    function scrollBottom() {
        messagesEl.scrollTo({ top: messagesEl.scrollHeight, behavior: 'smooth' });
    }

    /* ── Format timestamp ── */
    function timeStr() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    /* ── Render a message row ── */
    function addMessage(role, text) {
        const row = document.createElement('div');
        row.className = 'chat-row ' + role;

        const avatar = document.createElement('div');
        avatar.className = 'chat-avatar ' + (role === 'user' ? 'user-avatar' : 'ai-avatar');

        if (role === 'user') {
            avatar.textContent = initials;
        } else {
            avatar.innerHTML = `<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>`;
        }

        const wrap   = document.createElement('div');
        wrap.style.cssText = 'max-width:75%;display:flex;flex-direction:column;' + (role === 'user' ? 'align-items:flex-end;' : '');

        const bubble = document.createElement('div');
        bubble.className = 'bubble ' + (role === 'user' ? 'user-bubble' : 'assistant-bubble');
        bubble.style.whiteSpace = 'pre-wrap';
        bubble.textContent = text;

        const time = document.createElement('div');
        time.className = 'bubble-time';
        time.textContent = (role === 'user' ? 'You' : 'Herald AI') + ' • ' + timeStr();

        wrap.appendChild(bubble);
        wrap.appendChild(time);
        row.appendChild(avatar);
        row.appendChild(wrap);
        messagesEl.appendChild(row);
        scrollBottom();
        return row;
    }

    /* ── Sending state ── */
    function setSending(active) {
        isSending          = active;
        sendBtn.disabled   = active;
        inputEl.disabled   = active;
        sendIcon.style.display   = active ? 'none'  : '';
        sendSpinner.style.display = active ? ''     : 'none';
        typingRow.style.display  = active ? 'flex'  : 'none';
        if (active) scrollBottom();
    }

    /* ── Send message ── */
    async function sendMessage(text) {
        text = (text || inputEl.value).trim();
        if (!text || isSending) return;

        // Hide suggestions after first message
        suggestions.style.display = 'none';

        addMessage('user', text);
        messages.push({ role: 'user', content: text });
        inputEl.value = '';
        autoGrow();
        setSending(true);

        try {
            const res  = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messages }),
            });
            const data = await res.json();

            if (!res.ok || data.error) {
                throw new Error(data.error?.message || data.message || 'Request failed');
            }

            const reply = data.choices?.[0]?.message?.content;
            if (!reply || typeof reply !== 'string') throw new Error('Unexpected response format');

            addMessage('assistant', reply.trim());
            messages.push({ role: 'assistant', content: reply.trim() });

        } catch (err) {
            addMessage('assistant', '⚠️ Sorry, I ran into a problem: ' + (err.message || 'Unknown error') + '\n\nPlease try again in a moment.');
        } finally {
            setSending(false);
            inputEl.focus();
        }
    }

    /* ── Event listeners ── */
    sendBtn.addEventListener('click', () => sendMessage());

    inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    document.querySelectorAll('.suggest-chip').forEach(chip => {
        chip.addEventListener('click', () => sendMessage(chip.dataset.msg));
    });

    clearBtn.addEventListener('click', () => {
        if (!confirm('Clear the chat history?')) return;
        messages = [];
        // Remove all rows except welcome message
        const allRows = messagesEl.querySelectorAll('.chat-row:not(#welcome-msg)');
        allRows.forEach(r => r.remove());
        suggestions.style.display = 'flex';
    });

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
