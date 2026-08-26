function openMsgModal(id) {
    const overlay = document.getElementById('modal-' + id);
    if (!overlay) return;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    // Focus the close button for accessibility
    overlay.querySelector('.msg-modal-close')?.focus();
}

function closeById(id) {
    const overlay = document.getElementById('modal-' + id);
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

function closeMsgModal(event, id) {
    // Only close if clicking the backdrop, not the modal itself
    if (event.target === event.currentTarget) {
        closeById(id);
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.msg-modal-overlay.active').forEach(function(overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});
// ── Search: Escape clears, auto-focus cursor to end ──────────────────────
(function () {
    const input = document.getElementById('msgSearchInput');
    if (!input) return;
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            input.value = '';
            input.closest('form').submit();
        }
    });
    if (input.value) {
        input.focus();
        const len = input.value.length;
        input.setSelectionRange(len, len);
    }
})();
