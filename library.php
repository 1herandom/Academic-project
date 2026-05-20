<?php
require_once __DIR__ . '/includes/header.php';
require_login();
?>



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
    <div class="bk-modal" id="bkModal">
        <!-- Close button row -->
        <div class="bk-modal-header">
            <button class="bk-close-btn" onclick="closeBkModal()" aria-label="Close">✕</button>
        </div>
        <!-- Cover + title block -->
        <div class="bk-modal-top">
            <div class="bk-cover" id="bkCover">
                <div class="bk-cover-ph" id="bkCoverPh">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <img id="bkCoverImg" src="" alt="" style="display:none;" onload="this.style.display='block'; document.getElementById('bkCoverPh').style.display='none';" onerror="this.style.display='none'; document.getElementById('bkCoverPh').style.display='flex';">
            </div>
            <div class="bk-modal-meta">
                <div class="bk-modal-title" id="bkTitle"></div>
                <div class="bk-modal-author" id="bkAuthor"></div>
                <div class="bk-modal-year" id="bkYear"></div>
            </div>
        </div>
        <!-- Subject tags -->
        <div class="bk-tags" id="bkSubjects"></div>
        <!-- Divider + description -->
        <div class="bk-divider" id="bkDivider" style="display:none;"></div>
        <div class="bk-desc" id="bkDesc" style="display:none;"></div>
        <!-- Actions -->
        <div class="bk-actions">
            <a id="bkReadLink" class="btn sm" href="#" target="_blank" rel="noopener" style="display:none;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;margin-right:5px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Read on Internet Archive
            </a>
            <span id="bkNoRead" style="font-size:0.8rem; color:var(--text-faint); display:none;">Not available to read online</span>
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

    // Cover – update the persistent img/placeholder elements
    const coverImg = document.getElementById('bkCoverImg');
    const coverPh  = document.getElementById('bkCoverPh');
    // Reset state first
    coverImg.style.display = 'none';
    coverPh.style.display  = 'flex';
    if (coverId) {
        coverImg.alt = title;
        coverImg.src = `https://covers.openlibrary.org/b/id/${coverId}-L.jpg`;
    } else {
        coverImg.src = '';
    }

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

    // Internet Archive read link
    const readLink  = document.getElementById('bkReadLink');
    const noReadMsg = document.getElementById('bkNoRead');
    if (book.ia && book.has_fulltext) {
        readLink.href  = `https://archive.org/details/${book.ia[0]}`;
        readLink.style.display = 'inline-flex';
        noReadMsg.style.display = 'none';
    } else {
        readLink.style.display = 'none';
        noReadMsg.style.display = 'inline-block';
    }

    // Clear description & divider, show modal
    const descEl    = document.getElementById('bkDesc');
    const dividerEl = document.getElementById('bkDivider');
    descEl.style.display    = 'none';
    descEl.textContent      = '';
    dividerEl.style.display = 'none';
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
                descEl.textContent   = desc.length > 600 ? desc.substring(0, 600) + '…' : desc;
                descEl.style.display = 'block';
                document.getElementById('bkDivider').style.display = 'block';
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
