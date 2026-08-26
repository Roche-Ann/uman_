// ===== LIVE CLOCK =====
// Reads window.DASH_CLOCK_CONFIG, set inline in dashboard.php from PHP session settings.
(function () {
    const config = window.DASH_CLOCK_CONFIG;
    if (!config) return;

    const use12h   = config.use12h;
    const timezone = config.timezone;

    function tick() {
        const now = new Date();
        const opts = {
            timeZone: timezone,
            hour:     '2-digit',
            minute:   '2-digit',
            second:   '2-digit',
            hour12:   use12h,
        };
        const timeEl = document.getElementById('dashTime');
        if (timeEl) {
            timeEl.textContent = new Intl.DateTimeFormat('en-PH', opts).format(now);
        }
    }
    tick();
    setInterval(tick, 1000);
})();

// ===== DASHBOARD CHARTS =====
// Reads window.DASH_CHART_DATA, set inline in dashboard.php from PHP query results.
(function () {
    const data = window.DASH_CHART_DATA;
    if (!data || typeof Chart === 'undefined') return;

    const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };

    // Status Pie
    const statusEl = document.getElementById('statusPieChart');
    if (statusEl) {
        new Chart(statusEl, {
            type: 'doughnut',
            data: {
                labels: data.statusLabels,
                datasets: [{ data: data.statusCounts, backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ef4444'] }]
            },
            options: commonOptions
        });
    }

    // Project Pie
    const landUseEl = document.getElementById('landUsePieChart');
    if (landUseEl) {
        new Chart(landUseEl, {
            type: 'pie',
            data: {
                labels: data.landLabels,
                datasets: [{ data: data.landCounts, backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#6366f1'] }]
            },
            options: commonOptions
        });
    }

    // Barangay Bar
    const brgyEl = document.getElementById('barangayChart');
    if (brgyEl) {
        new Chart(brgyEl, {
            type: 'bar',
            data: {
                labels: data.brgyLabels,
                datasets: [{ label: data.brgyLabel, data: data.brgyCounts, backgroundColor: '#3b82f6', borderRadius: 5 }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Trend Line
    const trendEl = document.getElementById('trendChart');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'line',
            data: {
                labels: data.monthLabels,
                datasets: [{ label: data.monthLabel, data: data.monthCounts, borderColor: '#3b82f6', tension: 0.4, fill: true, backgroundColor: 'rgba(59, 130, 246, 0.1)' }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
})();