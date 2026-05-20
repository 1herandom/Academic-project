<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Student');

$pdo = db();
$studentId = current_user()['id'];

$stmt = $pdo->prepare("
    SELECT m.*, c.course_code, c.course_title, t.first_name, t.last_name
    FROM materials m
    JOIN courses c ON c.id = m.course_id
    JOIN enrollments e ON e.course_id = c.id
    LEFT JOIN teachers t ON t.id = m.created_by
    WHERE e.student_user_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$studentId]);
$materials = $stmt->fetchAll();
?>

<style>
/* ── Viewer Modal ─────────────────────────────────────── */
.viewer-backdrop {
    position: fixed;
    inset: 0;
    background: var(--bg-color, #fff);
    z-index: 200;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.25s ease, visibility 0.25s ease;
    padding: 1rem 1rem 1rem 4rem;
}
.viewer-backdrop.open {
    opacity: 1;
    visibility: visible;
}
.viewer-box {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    width: 100%;
    max-width: 1400px;
    max-height: 98vh;
    display: flex;
    flex-direction: column;
    transform: translateY(12px);
    transition: transform 0.28s cubic-bezier(0.175,0.885,0.32,1.275);
    overflow: hidden;
}
.viewer-backdrop.open .viewer-box {
    transform: translateY(0);
}
.viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    gap: 1rem;
    flex-shrink: 0;
}
.viewer-title {
    font-weight: 700;
    font-size: 1rem;
    color: var(--text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.viewer-meta {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 2px;
}
.viewer-close {
    background: none;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--text-muted);
    flex-shrink: 0;
    transition: background 0.2s, color 0.2s;
}
.viewer-close:hover {
    background: var(--surface-hover);
    color: var(--text-main);
}
.viewer-body {
    flex: 1;
    overflow: auto;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.viewer-iframe {
    width: 100%;
    flex: 1;
    border: none;
    min-height: 600px;
    background: #fff;
}
.viewer-unsupported {
    padding: 3rem 2rem;
    text-align: center;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.viewer-unsupported svg {
    width: 48px; height: 48px;
    opacity: 0.5;
}
.viewer-footer {
    padding: 0.75rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-shrink: 0;
}
/* View button colour */
.btn.view-btn {
    background: rgba(104, 186, 127, 0.12);
    color: #68BA7F;
    border: 1px solid rgba(104, 186, 127, 0.25);
}
.btn.view-btn:hover {
    background: rgba(104, 186, 127, 0.22);
}
</style>

<div class="page-hd">
    <h1>Course Materials</h1>
    <p>View or download lecture notes, lab resources, and reading materials for your enrolled courses.</p>
</div>

<div class="panel">
    <div style="margin-bottom: 1.5rem; position: relative;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--text-faint);">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" id="materialSearch" class="input" placeholder="Search materials by title, course, or category..." style="padding-left:38px;" onkeyup="filterMaterials()">
    </div>

    <div class="table-wrap">
        <table id="materialsTable">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Material</th>
                    <th>Category</th>
                    <th>Uploaded By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materials)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding: 32px 0; color: var(--text-muted);">
                        No materials available yet.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($materials as $m): ?>
                    <?php
                        $viewUrl  = APP_BASE_URL . '/includes/view_file.php?file=' . urlencode($m['file_path'] ?? '');
                        $fileUrl  = APP_BASE_URL . '/' . esc($m['file_path'] ?? '');
                        $fileType = strtoupper($m['file_type'] ?? '');
                        $canView  = in_array($fileType, ['PDF', 'MP4'], true) || !empty($m['video_link']);
                    ?>
                    <tr>
                        <td><span class="pill muted" style="font-family:monospace;"><?= esc($m['course_code']) ?></span></td>
                        <td><strong><?= esc($m['title']) ?></strong></td>
                        <td><span class="pill blue"><?= esc($m['category']) ?></span></td>
                        <td><?= esc($m['first_name'] . ' ' . $m['last_name']) ?></td>
                        <td style="display:flex; gap:6px; flex-wrap:wrap;">
                            <?php if ($canView): ?>
                            <button class="btn sm view-btn"
                                onclick="openViewer(
                                    <?= htmlspecialchars(json_encode($m['title']), ENT_QUOTES) ?>,
                                    <?= htmlspecialchars(json_encode($fileType), ENT_QUOTES) ?>,
                                    <?= htmlspecialchars(json_encode($viewUrl), ENT_QUOTES) ?>,
                                    <?= htmlspecialchars(json_encode($m['video_link'] ?? ''), ENT_QUOTES) ?>,
                                    <?= htmlspecialchars(json_encode($m['course_code'] . ' · ' . ucfirst(strtolower($fileType))), ENT_QUOTES) ?>
                                )">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;margin-right:4px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── PDF / Video Viewer Modal ──────────────────────── -->
<div class="viewer-backdrop" id="viewerBackdrop">
    <div class="viewer-box">
        <div class="viewer-header">
            <div style="overflow:hidden;">
                <div class="viewer-title" id="viewerTitle">—</div>
                <div class="viewer-meta" id="viewerMeta"></div>
            </div>
            <button class="viewer-close" onclick="closeViewer()" aria-label="Close viewer">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18" height="18">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="viewer-body" id="viewerBody">
            <!-- content injected by JS -->
        </div>
        <div class="viewer-footer" id="viewerFooter">
            <a id="viewerDownloadBtn" class="btn green sm" href="#" download style="display:none;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;margin-right:4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download
            </a>
            <button class="btn secondary sm" onclick="closeViewer()">Close</button>
        </div>
    </div>
</div>

<script>
function openViewer(title, type, fileUrl, videoLink, meta) {
    document.getElementById('viewerTitle').textContent = title;
    document.getElementById('viewerMeta').textContent  = meta;

    const body  = document.getElementById('viewerBody');
    const dlBtn = document.getElementById('viewerDownloadBtn');
    body.innerHTML = '';
    dlBtn.style.display = 'none';

    if (type === 'PDF' && fileUrl) {
        const iframe = document.createElement('iframe');
        iframe.src = fileUrl;
        iframe.className = 'viewer-iframe';
        iframe.title = title;
        body.appendChild(iframe);
        dlBtn.href = fileUrl;
        dlBtn.style.display = 'inline-flex';
    } else if (type === 'MP4') {
        const src = fileUrl || videoLink;
        if (src && src.match(/youtube\.com|youtu\.be/)) {
            let vid = src.replace('watch?v=','embed/').replace('youtu.be/','www.youtube.com/embed/');
            const iframe = document.createElement('iframe');
            iframe.src = vid + '?rel=0&autoplay=1';
            iframe.className = 'viewer-iframe';
            iframe.allow = 'autoplay; fullscreen; encrypted-media';
            iframe.allowFullscreen = true;
            body.appendChild(iframe);
        } else if (fileUrl) {
            const video = document.createElement('video');
            video.src = fileUrl;
            video.controls = true;
            video.style.cssText = 'width:100%;max-height:540px;background:#000;';
            body.appendChild(video);
            dlBtn.href = fileUrl;
            dlBtn.style.display = 'inline-flex';
        } else if (videoLink) {
            body.innerHTML = `<div class="viewer-unsupported"><p>Externally hosted video.</p><a href="${videoLink}" target="_blank" rel="noopener" class="btn sm">Open External Link</a></div>`;
        }
    } else {
        body.innerHTML = `<div class="viewer-unsupported"><p style="color:var(--text-muted);margin:0;">Preview not available for this file type. Use Download to view.</p></div>`;
        if (fileUrl) { dlBtn.href = fileUrl; dlBtn.style.display = 'inline-flex'; }
    }

    document.getElementById('viewerBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeViewer() {
    document.getElementById('viewerBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(() => { document.getElementById('viewerBody').innerHTML = ''; }, 300);
}

document.getElementById('viewerBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeViewer();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeViewer();
});

function filterMaterials() {
    const q = document.getElementById('materialSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#materialsTable tbody tr');
    let hasResults = false;

    rows.forEach(row => {
        if (row.cells.length < 4) return;
        const text = row.innerText.toLowerCase();
        if (text.includes(q)) {
            row.style.display = '';
            hasResults = true;
        } else {
            row.style.display = 'none';
        }
    });

    let emptyMsg = document.getElementById('noResultsRow');
    if (!hasResults) {
        if (!emptyMsg) {
            emptyMsg = document.createElement('tr');
            emptyMsg.id = 'noResultsRow';
            emptyMsg.innerHTML = `<td colspan="5" style="text-align:center; padding:32px 0; color:var(--text-muted);">No matching materials found.</td>`;
            document.querySelector('#materialsTable tbody').appendChild(emptyMsg);
        }
        emptyMsg.style.display = '';
    } else if (emptyMsg) {
        emptyMsg.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


