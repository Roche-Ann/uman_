function backToStep1() {
    document.querySelectorAll('.step').forEach(function (el) { el.classList.remove('active'); });
    document.getElementById('step1').classList.add('active');
    document.getElementById('payBtn').style.display = 'none';
    document.getElementById('payment_category').value = '';
    document.getElementById('payment_provider').value = '';
}

function chooseCategory(cat) {
    document.querySelectorAll('.step').forEach(function (el) { el.classList.remove('active'); });
    document.getElementById('step2-' + cat).classList.add('active');
    document.getElementById('payment_category').value = cat;

    // Auto-select the first provider in this category.
    var firstChip = document.querySelector('.provider-chip[data-category="' + cat + '"]');
    if (firstChip) {
        chooseProvider(cat, firstChip.getAttribute('data-provider'));
    }
}

function chooseProvider(cat, provider) {
    document.querySelectorAll('.provider-chip[data-category="' + cat + '"]').forEach(function (chip) {
        chip.classList.toggle('selected', chip.getAttribute('data-provider') === provider);
    });
    document.getElementById('payment_provider').value = provider;
    document.getElementById('payBtn').style.display = 'block';
}

// Simple loading state so the simulated server-side delay feels intentional.
var form = document.getElementById('payForm');
if (form) {
    form.addEventListener('submit', function () {
        var btn = document.getElementById('payBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing payment...';
    });
}
