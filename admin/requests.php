<?php
/*
|------------------------------------------------------------------------------------
  Feature By | Bipin Guragain: Password reset request management & Approve/Reset Workflow
|------------------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
require_role('Academic Admin');

$pdo = db();

// Ensure password_requests table exists (auto-migration)
try {
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

// ── POST handlers ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Approve & Generate Temp Password ─────────────────────────────────────
    if (isset($_POST['approve_id'])) {
        $approveId = (int)$_POST['approve_id'];
        $reqStmt   = $pdo->prepare("SELECT * FROM password_requests WHERE id = ?");
        $reqStmt->execute([$approveId]);
        $req = $reqStmt->fetch();

        if ($req) {
            $tempPwd = generate_temp_password();

            // Try to update the actual user's password in the system
            $userUpdated = false;
            foreach (['admins' => 'Academic Admin', 'teachers' => 'Teacher', 'students' => 'Student'] as $tbl => $role) {
                // Match by institutional_id first, then email
                $findStmt = $pdo->prepare("SELECT id FROM {$tbl} WHERE institutional_id = ? OR email = ? LIMIT 1");
                $findStmt->execute([$req['student_id'], $req['email']]);
                $foundUser = $findStmt->fetch();
                if ($foundUser) {
                    $pdo->prepare("UPDATE {$tbl} SET password_hash = ?, temp_password = 1 WHERE id = ?")
                        ->execute([password_hash($tempPwd, PASSWORD_BCRYPT), $foundUser['id']]);
                    $userUpdated = true;
                    break;
                }
            }

            // Save temp password in the request row
            $pdo->prepare("UPDATE password_requests SET status = 'approved', temp_password = ? WHERE id = ?")
                ->execute([$tempPwd, $approveId]);

            if ($userUpdated) {
                flash_set('success', "Password reset approved. Temporary password: {$tempPwd} — Share this securely with the user.");
            } else {
                flash_set('info', "Temporary password generated: {$tempPwd} — No matching user account was found to auto-update. Provide it manually.");
            }
        }
        redirect('/admin/requests.php');
    }

    // ── Send Password to Personal Email ──────────────────────────────────────
    if (isset($_POST['send_email_id'])) {
        $sendId  = (int)$_POST['send_email_id'];
        $reqStmt = $pdo->prepare("SELECT * FROM password_requests WHERE id = ? AND status = 'approved'");
        $reqStmt->execute([$sendId]);
        $req = $reqStmt->fetch();

        if ($req && !empty($req['temp_password'])) {
            // Build email
            $html = build_password_reset_email(
                htmlspecialchars($req['full_name'], ENT_QUOTES, 'UTF-8'),
                $req['temp_password'],
                htmlspecialchars($req['email'], ENT_QUOTES, 'UTF-8')
            );
            $result = send_smtp_email(
                $req['email'],
                htmlspecialchars($req['full_name'], ENT_QUOTES, 'UTF-8'),
                'Your Herald Password Has Been Reset',
                $html
            );

            if ($result === true) {
                flash_set('success', "✅ Password reset email sent successfully to {$req['email']}.");
            } else {
                flash_set('error', "❌ Email failed to send: {$result}");
            }
        } else {
            flash_set('error', 'Request not found or password not yet generated. Please approve first.');
        }
        redirect('/admin/requests.php');
    }

    // ── Resolve support_request (legacy) ─────────────────────────────────────
    if (isset($_POST['resolve_id'])) {
        $resolveId = (int)$_POST['resolve_id'];
        $pdo->prepare("UPDATE support_requests SET status = 'resolved' WHERE id = ?")
            ->execute([$resolveId]);
        flash_set('success', 'Request marked as resolved.');
        redirect('/admin/requests.php');
    }

    // ── Delete support_request (legacy) ──────────────────────────────────────
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $pdo->prepare("DELETE FROM support_requests WHERE id = ?")->execute([$deleteId]);
        flash_set('success', 'Request deleted.');
        redirect('/admin/requests.php');
    }

    // ── Reject password_request ───────────────────────────────────────────────
    if (isset($_POST['reject_id'])) {
        $rejectId = (int)$_POST['reject_id'];
        $pdo->prepare("UPDATE password_requests SET status = 'rejected' WHERE id = ?")
            ->execute([$rejectId]);
        flash_set('success', 'Request rejected.');
        redirect('/admin/requests.php');
    }

    redirect('/admin/requests.php');
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$passwordRequests = $pdo->query(
    "SELECT * FROM password_requests ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC"
)->fetchAll();

$legacyRequests = $pdo->query(
    "SELECT * FROM support_requests ORDER BY FIELD(status,'pending','resolved'), created_at DESC"
)->fetchAll();

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM password_requests WHERE status='pending'")->fetchColumn();
?>

<style>
/* Feature Maker: Bipin Guragain | Feature: Admin Password Request Board */
.req-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.req-badge.pending  { background: rgba(234,179,8,.15); color: #d97706; }
.req-badge.approved { background: rgba(34,197,94,.15);  color: #16a34a; }
.req-badge.rejected { background: rgba(239,68,68,.15);  color: #dc2626; }
.req-badge.resolved { background: rgba(99,102,241,.15); color: #4f46e5; }
.req-badge.password_reset { background: rgba(59,130,246,.12); color: #2563eb; }
.req-badge.general       { background: rgba(107,114,128,.12); color: #6b7280; }

.action-row { display: flex; gap: 6px; flex-wrap: wrap; }

.temp-pwd-box {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(0,0,0,.06);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 4px 10px;
    font-family: monospace;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 2px;
    color: var(--herald-green);
}
</style>

<!-- Page header -->
<div class="page-header-layout">
    <div>
        <h1 class="page-title">
            Password Reset Requests
            <?php if ($pendingCount > 0): ?>
                <span style="display:inline-flex;align-items:center;justify-content:center;background:var(--herald-gold);color:#000;font-size:.7rem;font-weight:800;border-radius:999px;padding:2px 8px;margin-left:8px;vertical-align:middle;"><?= $pendingCount ?> Pending</span>
            <?php endif; ?>
        </h1>
        <p class="muted page-subtitle">Review and approve password reset requests from students and faculty.</p>
    </div>
</div>

<!-- ═══ PASSWORD REQUESTS (New table) ════════════════════════════════════════ -->
<div class="panel" style="margin-top:20px;">
    <h3 class="panel-title" style="margin-top:0;">
        🔑 Password Reset Requests
        <span class="small muted" style="font-weight:400;">(from Forgot Password form)</span>
    </h3>

    <?php if (empty($passwordRequests)): ?>
        <p class="muted" style="text-align:center;padding:24px 0;">No password reset requests found.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Institutional Email</th>
                    <th>Student / Staff ID</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Temp Password</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($passwordRequests as $r): ?>
                <tr>
                    <td><?= esc(date('Y-m-d H:i', strtotime($r['created_at']))) ?></td>
                    <td><strong><?= esc($r['full_name']) ?></strong></td>
                    <td><a href="mailto:<?= esc($r['email']) ?>"><?= esc($r['email']) ?></a></td>
                    <td><code><?= esc($r['student_id']) ?></code></td>
                    <td><span class="req-badge <?= esc($r['request_type']) ?>"><?= $r['request_type'] === 'password_reset' ? '🔑 Reset' : '💬 General' ?></span></td>
                    <td><span class="req-badge <?= esc($r['status']) ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                    <td>
                        <?php if (!empty($r['temp_password'])): ?>
                            <span class="temp-pwd-box" id="tmpwd-<?= (int)$r['id'] ?>"><?= esc($r['temp_password']) ?></span>
                            <button class="btn secondary sm" style="min-height:28px;padding:2px 8px;font-size:11px;margin-top:4px;"
                                onclick="navigator.clipboard.writeText('<?= esc($r['temp_password']) ?>'); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy',1500)">Copy</button>
                        <?php else: ?>
                            <span class="muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-row">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="post">
                                    <input type="hidden" name="approve_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn sm"
                                            onclick="return confirm('Generate a temporary password for <?= esc(addslashes($r['full_name'])) ?>?')"
                                            title="Generate temp password and update user account">
                                        ✅ Approve &amp; Reset
                                    </button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="reject_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn sm danger"
                                            onclick="return confirm('Reject this request?')">Reject</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($r['status'] === 'approved' && !empty($r['temp_password'])): ?>
                                <form method="post">
                                    <input type="hidden" name="send_email_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn sm amber"
                                            onclick="return confirm('Send the temporary password to <?= esc(addslashes($r['email'])) ?>?')"
                                            title="Send to personal email">
                                        📧 Send to Personal Email
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ LEGACY SUPPORT REQUESTS ═════════════════════════════════════════════ -->
<div class="panel" style="margin-top:24px;">
    <h3 class="panel-title" style="margin-top:0;">
        💬 General Support Requests
        <span class="small muted" style="font-weight:400;">(Contact Admin form / legacy)</span>
    </h3>

    <?php if (empty($legacyRequests)): ?>
        <p class="muted" style="text-align:center;padding:24px 0;">No support requests found.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($legacyRequests as $r): ?>
                <tr>
                    <td><?= esc(date('Y-m-d H:i', strtotime($r['created_at']))) ?></td>
                    <td><strong><?= esc($r['full_name']) ?></strong></td>
                    <td><a href="mailto:<?= esc($r['email']) ?>"><?= esc($r['email']) ?></a></td>
                    <td style="max-width:320px;white-space:pre-wrap;"><?= esc($r['message']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <span class="req-badge pending">Pending</span>
                        <?php else: ?>
                            <span class="req-badge resolved">Resolved</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-row">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="post">
                                    <input type="hidden" name="resolve_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn sm">Resolve</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('Delete this request?')">
                                <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
