<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Student');
?>

<style>
/* ── Library Page ─────────────────────────────────────── */
.lib-search-bar {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.lib-search-bar .input {
    flex: 1;
    min-width: 200px;
}
.lib-filter-row {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.75rem;
    flex-wrap: wrap;
    align-items: center;
}
.lib-filter-row .input {
    min-width: 140px;
    max-width: 200px;
}
.lib-results-info {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}
.book-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 1.25rem;
}
.book-card {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    cursor: pointer;
}
.book-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    border-color: rgba(104,186,127,0.35);
}
.book-cover-wrap {
    position: relative;
    width: 100%;
    padding-top: 140%;
    background: rgba(104,186,127,0.06);
    overflow: hidden;
    flex-shrink: 0;
}
.book-cover-wrap img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.book-cover-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(104,186,127,0.3);
}
.book-cover-placeholder svg { width:52px; height:52px; }
.book-info {
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    flex: 1;
}
.book-title {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--text-main);
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.book-author {
    font-size: 0.78rem;
    color: var(--text-muted);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.book-year {
    font-size: 0.75rem;
    color: var(--text-faint);
    margin-top: auto;
    padding-top: 0.4rem;
}
.book-card-footer {
    padding: 0.6rem 0.85rem 0.85rem;
    display: flex;
    gap: 0.5rem;
}
.lib-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}
.lib-empty svg { width:52px; height:52px; opacity:0.35; margin-bottom:1rem; }
.lib-spinner {
    display: none;
    text-align: center;
    padding: 2.5rem;
    color: var(--text-muted);
    font-size: 0.9rem;
}
.lib-spinner.active { display: block; }
.lib-spinner-ring {
    width: 36px; height: 36px;
    border: 3px solid var(--border-color);
    border-top-color: #68BA7F;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 0.75rem;
}
@keyframes spin { to { transform: rotate(360deg); } }
.lib-pagination {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 2rem;
    flex-wrap: wrap;
}

/* ── Book Detail Modal ─────────────────────────────────── */
.bk-backdrop {
    position: fixed;
    inset: 0;
    background: transparent;
    z-index: 200;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.25s, visibility 0.25s;
}
.bk-backdrop.open { opacity:1; visibility:visible; }
.bk-modal {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    width: 100%;
    max-width: 640px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 2rem;
    transform: scale(0.96);
    transition: transform 0.28s cubic-bezier(0.175,0.885,0.32,1.275);
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.bk-backdrop.open .bk-modal { transform: scale(1); }
.bk-modal-top {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}
.bk-cover {
    width: 100px;
    flex-shrink: 0;
    border-radius: 6px;
    overflow: hidden;
    background: rgba(104,186,127,0.08);
    border: 1px solid var(--border-color);
}
.bk-cover img { width:100%; display:block; }
.bk-cover-ph {
    width:100%; aspect-ratio: 2/3;
    display:flex; align-items:center; justify-content:center;
    color: rgba(104,186,127,0.3);
}
.bk-cover-ph svg { width:36px; height:36px; }
.bk-modal-meta { flex:1; }
.bk-modal-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.3;
    margin-bottom: 0.4rem;
}
.bk-modal-author { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.3rem; }
.bk-modal-year   { color: var(--text-faint); font-size: 0.82rem; }
.bk-desc {
    font-size: 0.88rem;
    color: var(--text-muted);
    line-height: 1.65;
    border-top: 1px solid var(--border-color);
    padding-top: 1rem;
}
.bk-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.bk-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.25rem;
    flex-wrap: wrap;
}
.bk-close-btn {
    position: absolute;
    top: 1rem; right: 1rem;
}
</style>

<div class="page-hd">
    <h1>Open Library</h1>
    <p>Search millions of books from the Open Library catalogue. Browse, preview, and find reading resources.</p>
</div>

<div class="panel">
    <div class="lib-search-bar">
        <input class="input" id="libQuery" type="text"
               placeholder="Search by title, author, or keyword…"
               value="programming"
               onkeydown="if(event.key==='Enter') searchBooks(1)">
        <div class="lib-filter-row" style="margin:0; flex-wrap:nowrap; gap:0.75rem;">
            <select class="input" id="libType" style="min-width:130px;">
                <option value="">All fields</option>
                <option value="title">Title</option>
                <option value="author">Author</option>
                <option value="subject">Subject</option>
            </select>
            <button class="btn" onclick="searchBooks(1)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:15px;height:15px;margin-right:5px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Search
            </button>
        </div>
    </div>

    <div class="lib-spinner" id="libSpinner">
        <div class="lib-spinner-ring"></div>
        Searching Open Library…
    </div>

    <div id="libResultsInfo" class="lib-results-info" style="display:none;"></div>
    <div class="book-grid" id="bookGrid"></div>
    <div id="libPagination" class="lib-pagination"></div>

    <div class="lib-empty" id="libEmpty" style="display:none;">
        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <p style="margin:0;">No books found. Try a different search term.</p>
    </div>
</div>

<!-- ── Book Detail Modal ─────────────────────────────── -->
<div class="bk-backdrop" id="bkBackdrop">
    <div class="bk-modal" id="bkModal" style="position:relative;">
        <button class="btn secondary sm bk-close-btn" onclick="closeBkModal()">✕</button>
        <div class="bk-modal-top">
            <div class="bk-cover" id="bkCover"></div>
            <div class="bk-modal-meta">
                <div class="bk-modal-title" id="bkTitle"></div>
                <div class="bk-modal-author" id="bkAuthor"></div>
                <div class="bk-modal-year" id="bkYear"></div>
                <div class="bk-tags" id="bkSubjects" style="margin-top:0.6rem;"></div>
            </div>
        </div>
        <div class="bk-desc" id="bkDesc" style="display:none;"></div>
        <div class="bk-actions">
            <a id="bkOpenLibLink" class="btn sm" href="#" target="_blank" rel="noopener">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;margin-right:4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Open on Open Library
            </a>
            <a id="bkInternetArchiveLink" class="btn sm secondary" href="#" target="_blank" rel="noopener" style="display:none;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;margin-right:4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Read Free on Internet Archive
            </a>
        </div>
    </div>
</div>

<script>
const PER_PAGE = 20;
let currentPage = 1;
let lastTotal   = 0;

async function searchBooks(page) {
    page = page || 1;
    currentPage = page;

    const q      = document.getElementById('libQuery').value.trim();
    const type   = document.getElementById('libType').value;

    if (!q) return;

    setLoading(true);
    document.getElementById('bookGrid').innerHTML       = '';
    document.getElementById('libPagination').innerHTML  = '';
    document.getElementById('libResultsInfo').style.display = 'none';
    document.getElementById('libEmpty').style.display   = 'none';

    const field  = type ? `${type}=` : 'q=';
    const offset = (page - 1) * PER_PAGE;
    const url    = `https://openlibrary.org/search.json?${field}${encodeURIComponent(q)}&limit=${PER_PAGE}&offset=${offset}&fields=key,title,author_name,first_publish_year,cover_i,subject,ia,has_fulltext,edition_count,number_of_pages_median`;

    try {
        const res  = await fetch(url);
        const data = await res.json();
        lastTotal  = data.numFound || 0;

        setLoading(false);

        if (!data.docs || data.docs.length === 0) {
            document.getElementById('libEmpty').style.display = 'block';
            return;
        }

        const infoEl = document.getElementById('libResultsInfo');
        infoEl.textContent = `Showing ${offset + 1}–${Math.min(offset + data.docs.length, lastTotal)} of ${lastTotal.toLocaleString()} results`;
        infoEl.style.display = 'block';

        renderBooks(data.docs);
        renderPagination(lastTotal, page);

    } catch(err) {
        setLoading(false);
        document.getElementById('bookGrid').innerHTML =
            `<div class="lib-empty" style="grid-column:1/-1;display:flex;flex-direction:column;align-items:center;">
                <p style="color:var(--herald-red);">Failed to reach Open Library. Please check your connection and try again.</p>
            </div>`;
    }
}

function renderBooks(docs) {
    const grid = document.getElementById('bookGrid');
    grid.innerHTML = '';

    docs.forEach(book => {
        const title   = book.title || 'Untitled';
        const author  = book.author_name ? book.author_name.slice(0,2).join(', ') : 'Unknown author';
        const year    = book.first_publish_year || '';
        const coverId = book.cover_i;
        const coverSrc = coverId
            ? `https://covers.openlibrary.org/b/id/${coverId}-M.jpg`
            : null;

        const card = document.createElement('div');
        card.className = 'book-card';
        card.onclick   = () => openBookDetail(book);
        card.innerHTML = `
            <div class="book-cover-wrap">
                ${coverId
                    ? `<img src="${coverSrc}" alt="${escHtml(title)}" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\\'book-cover-placeholder\\'>${bookIconSVG()}</div>'">`
                    : `<div class="book-cover-placeholder">${bookIconSVG()}</div>`}
            </div>
            <div class="book-info">
                <div class="book-title">${escHtml(title)}</div>
                <div class="book-author">${escHtml(author)}</div>
                ${year ? `<div class="book-year">${year}</div>` : ''}
            </div>
        `;
        grid.appendChild(card);
    });
}

function renderPagination(total, currentPage) {
    const totalPages = Math.min(Math.ceil(total / PER_PAGE), 50); // OL caps at ~1000 results
    if (totalPages <= 1) return;

    const container = document.getElementById('libPagination');
    container.innerHTML = '';

    const mkBtn = (label, page, disabled, active) => {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.className = 'btn sm' + (active ? '' : ' secondary');
        btn.disabled  = disabled;
        btn.onclick   = () => { searchBooks(page); window.scrollTo({top:0,behavior:'smooth'}); };
        return btn;
    };

    container.appendChild(mkBtn('← Prev', currentPage - 1, currentPage === 1, false));

    const start = Math.max(1, currentPage - 2);
    const end   = Math.min(totalPages, currentPage + 2);
    for (let p = start; p <= end; p++) {
        container.appendChild(mkBtn(p, p, false, p === currentPage));
    }

    container.appendChild(mkBtn('Next →', currentPage + 1, currentPage >= totalPages, false));
}

async function openBookDetail(book) {
    const title   = book.title || 'Untitled';
    const author  = book.author_name ? book.author_name.join(', ') : 'Unknown author';
    const year    = book.first_publish_year ? `First published ${book.first_publish_year}` : '';
    const coverId = book.cover_i;
    const key     = book.key; // e.g. /works/OL12345W

    document.getElementById('bkTitle').textContent  = title;
    document.getElementById('bkAuthor').textContent = author;
    document.getElementById('bkYear').textContent   = year;

    // Cover
    const coverEl = document.getElementById('bkCover');
    coverEl.innerHTML = coverId
        ? `<img src="https://covers.openlibrary.org/b/id/${coverId}-L.jpg" alt="${escHtml(title)}" onerror="this.parentElement.innerHTML='<div class=\\'bk-cover-ph\\'>${bookIconSVG()}</div>'">`
        : `<div class="bk-cover-ph">${bookIconSVG()}</div>`;

    // Subjects
    const subjectsEl = document.getElementById('bkSubjects');
    subjectsEl.innerHTML = '';
    if (book.subject) {
        book.subject.slice(0, 6).forEach(s => {
            const span = document.createElement('span');
            span.className = 'pill muted';
            span.style.fontSize = '0.72rem';
            span.textContent = s;
            subjectsEl.appendChild(span);
        });
    }

    // Open Library link
    document.getElementById('bkOpenLibLink').href = `https://openlibrary.org${key}`;

    // Internet Archive (free read)
    const iaLink = document.getElementById('bkInternetArchiveLink');
    if (book.ia && book.has_fulltext) {
        iaLink.href  = `https://archive.org/details/${book.ia[0]}`;
        iaLink.style.display = 'inline-flex';
    } else {
        iaLink.style.display = 'none';
    }

    // Clear description, show modal
    const descEl = document.getElementById('bkDesc');
    descEl.style.display = 'none';
    descEl.textContent   = '';
    document.getElementById('bkBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';

    // Fetch description async
    if (key) {
        try {
            const res  = await fetch(`https://openlibrary.org${key}.json`);
            const work = await res.json();
            let desc   = work.description;
            if (desc && typeof desc === 'object') desc = desc.value;
            if (desc) {
                descEl.textContent   = desc.length > 700 ? desc.substring(0, 700) + '…' : desc;
                descEl.style.display = 'block';
            }
        } catch(e) { /* silent */ }
    }
}

function closeBkModal() {
    document.getElementById('bkBackdrop').classList.remove('open');
    document.body.style.overflow = '';
}

document.getElementById('bkBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeBkModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeBkModal(); });

function setLoading(on) {
    document.getElementById('libSpinner').className = 'lib-spinner' + (on ? ' active' : '');
}

function escHtml(str) {
    return str.replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function bookIconSVG() {
    return `<svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>`.replace(/"/g, "'");
}

// Auto-search on load
searchBooks(1);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
