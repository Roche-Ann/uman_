/* Switch between table and card layout based on viewport width */
(function () {
    const tableCard   = document.querySelector('.apps-page > .card');
    const mobileCards = document.getElementById('app-cards-mobile');

    function applyLayout() {
        if (window.innerWidth <= 480) {
            if (tableCard)   tableCard.classList.add('d-none');
            if (mobileCards) mobileCards.classList.remove('d-none');
        } else {
            if (tableCard)   tableCard.classList.remove('d-none');
            if (mobileCards) mobileCards.classList.add('d-none');
        }
    }

    applyLayout();
    window.addEventListener('resize', applyLayout);
})();
