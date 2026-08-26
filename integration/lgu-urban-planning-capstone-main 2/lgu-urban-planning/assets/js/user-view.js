function openDocModal(viewUrl, fileName, ext) {
    // Reset
    document.getElementById('docViewerFrame').style.display    = 'none';
    document.getElementById('docViewerImg').style.display      = 'none';
    document.getElementById('docViewerUnsupported').style.display = 'none';
    document.getElementById('docViewerSpinner').style.display  = 'block';
    document.getElementById('docViewerFrame').src              = '';
    document.getElementById('docViewerImg').src                = '';

    document.getElementById('docViewerTitle').textContent = fileName;

    // Download link (no &view=1)
    const downloadUrl = viewUrl.replace('&view=1', '');
    document.getElementById('docViewerDownload').href = downloadUrl;
    document.getElementById('docViewerUnsupportedLink').href = downloadUrl;

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('docViewerModal'));
    modal.show();

    const imageExts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    if (ext === 'pdf') {
        const frame = document.getElementById('docViewerFrame');
        frame.onload = () => {
            document.getElementById('docViewerSpinner').style.display = 'none';
            frame.style.display = 'block';
        };
        frame.src = viewUrl;

    } else if (imageExts.includes(ext)) {
        const img = document.getElementById('docViewerImg');
        img.onload = () => {
            document.getElementById('docViewerSpinner').style.display = 'none';
            img.style.display = 'block';
        };
        img.src = viewUrl;

    } else {
        // Unsupported type — show download prompt
        document.getElementById('docViewerSpinner').style.display    = 'none';
        document.getElementById('docViewerUnsupported').style.display = 'block';
    }
}

// ── Status History Pagination ────────────────────────────────────────────────
(function () {
    const ITEMS_PER_PAGE = 5;
    let currentPage = 1;

    const items      = Array.from(document.querySelectorAll('.history-item'));
    const pagination = document.getElementById('historyPagination');
    const prevBtn    = document.getElementById('historyPrevBtn');
    const nextBtn    = document.getElementById('historyNextBtn');
    const pageLabel  = document.getElementById('historyPaginationLabel');
    const pageInfo   = document.getElementById('historyPageInfo');

    const totalPages = Math.ceil(items.length / ITEMS_PER_PAGE);

    function renderPage(page) {
        const start = (page - 1) * ITEMS_PER_PAGE;
        const end   = start + ITEMS_PER_PAGE;

        items.forEach(function (item, idx) {
            item.style.display = (idx >= start && idx < end) ? '' : 'none';
        });

        prevBtn.disabled = (page <= 1);
        nextBtn.disabled = (page >= totalPages);

        const label = 'Page ' + page + ' of ' + totalPages;
        pageLabel.textContent = label;
        pageInfo.textContent  = items.length + ' entr' + (items.length === 1 ? 'y' : 'ies');
    }

    if (items.length > ITEMS_PER_PAGE) {
        pagination.style.removeProperty('display'); // show the nav
        renderPage(currentPage);
    } else {
        // Still show info label even when no pagination needed
        pageInfo.textContent = items.length + ' entr' + (items.length === 1 ? 'y' : 'ies');
    }

    window.changeHistoryPage = function (direction) {
        const next = currentPage + direction;
        if (next < 1 || next > totalPages) return;
        currentPage = next;
        renderPage(currentPage);

        // Scroll to top of the history card smoothly
        document.getElementById('historyTimeline')
                .closest('.card')
                .scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
})();

// Clear iframe/img src when modal closes to stop loading
document.getElementById('docViewerModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('docViewerFrame').src = '';
    document.getElementById('docViewerImg').src   = '';
});
