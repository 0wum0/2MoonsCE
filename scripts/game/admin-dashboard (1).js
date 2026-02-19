/**
 * SmartMoons Admin Dashboard JS - SM-3.1.0
 * Handles: Sidebar toggle, Period tabs, Flipcards, Charts
 */

(function() {
    'use strict';

    // ---- Sidebar Toggle ----
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const hamburger = document.getElementById('hamburgerBtn');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (hamburger) hamburger.addEventListener('click', openSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Close sidebar on link click (mobile)
    document.querySelectorAll('.sidebar-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });

    // ---- Flipcard Touch Toggle ----
    document.querySelectorAll('.flipcard').forEach(function(card) {
        card.addEventListener('click', function(e) {
            // On touch devices, toggle flipped class
            if (window.matchMedia('(hover: none)').matches) {
                this.classList.toggle('flipped');
            }
        });
    });

    // ---- Period Tabs ----
    const periodTabs = document.querySelectorAll('.period-tab');

    periodTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var period = this.getAttribute('data-period');
            periodTabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            loadDashboardData(period);
        });
    });

    function loadDashboardData(period) {
        // AJAX request to get period data
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'admin.php?page=overview&ajax=1&period=' + encodeURIComponent(period), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    updateKPIs(data.report);
                    updateCharts(data.charts);
                    updateReport(data.report);
                } catch (e) {
                    console.error('Dashboard data parse error:', e);
                }
            }
        };
        xhr.send();
    }

    function formatNumber(n) {
        if (n === null || n === undefined || n === -1) return 'n/a';
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function updateKPIs(report) {
        if (!report) return;

        var mapping = {
            'kpi-online': report.players_online ? (report.players_online.online + ' / ' + report.players_online.total) : 'n/a',
            'kpi-registrations': report.registrations ? formatNumber(report.registrations.count) : 'n/a',
            'kpi-fleets': report.fleets_sent ? formatNumber(report.fleets_sent.count) : 'n/a',
            'kpi-alliances': report.alliances_founded ? formatNumber(report.alliances_founded.count) : 'n/a',
            'kpi-combats': report.combats ? formatNumber(report.combats.count) : 'n/a',
            'kpi-messages': report.messages ? formatNumber(report.messages.count) : 'n/a',
            'kpi-multi': report.multiaccount_flags ? formatNumber(report.multiaccount_flags.flagged_ips) : 'n/a',
            'kpi-banned': report.banned_players ? formatNumber(report.banned_players.count) : 'n/a',
            'kpi-tickets': report.open_tickets ? formatNumber(report.open_tickets.count) : 'n/a',
            'kpi-planets': report.planets_total ? formatNumber(report.planets_total.count) : 'n/a',
        };

        for (var key in mapping) {
            var el = document.getElementById(key);
            if (el) el.textContent = mapping[key];
        }

        // Update top players
        var topList = document.getElementById('top-players-list');
        if (topList && report.top_players) {
            var html = '';
            report.top_players.forEach(function(p, i) {
                var rankClass = i === 0 ? 'gold' : (i === 1 ? 'silver' : (i === 2 ? 'bronze' : ''));
                html += '<div class="top-player-item">';
                html += '<div class="top-player-rank ' + rankClass + '">' + (i + 1) + '</div>';
                html += '<div class="top-player-name">' + escapeHtml(p.username || 'Unknown') + '</div>';
                html += '<div class="top-player-points">' + formatNumber(parseInt(p.points || 0)) + ' Pkt</div>';
                html += '</div>';
            });
            topList.innerHTML = html;
        }

        // Update active players
        var activeList = document.getElementById('active-players-list');
        if (activeList && report.active_players) {
            var html2 = '';
            report.active_players.forEach(function(p, i) {
                var rankClass = i === 0 ? 'gold' : (i === 1 ? 'silver' : (i === 2 ? 'bronze' : ''));
                html2 += '<div class="top-player-item">';
                html2 += '<div class="top-player-rank ' + rankClass + '">' + (i + 1) + '</div>';
                html2 += '<div class="top-player-name">' + escapeHtml(p.username || 'Unknown') + '</div>';
                html2 += '<div class="top-player-points">' + formatTimestamp(p.onlinetime) + '</div>';
                html2 += '</div>';
            });
            activeList.innerHTML = html2;
        }

        // Update report values
        var reportMapping = {
            'rpt-online-peak': report.players_online ? report.players_online.online : 'n/a',
            'rpt-new-players': report.registrations ? formatNumber(report.registrations.count) : 'n/a',
            'rpt-fleets-sent': report.fleets_sent ? formatNumber(report.fleets_sent.count) : 'n/a',
            'rpt-combats': report.combats ? formatNumber(report.combats.count) : 'n/a',
            'rpt-messages': report.messages ? formatNumber(report.messages.count) : 'n/a',
            'rpt-alliances': report.alliances_founded ? formatNumber(report.alliances_founded.count) : 'n/a',
            'rpt-multi-ips': report.multiaccount_flags ? formatNumber(report.multiaccount_flags.flagged_ips) : 'n/a',
            'rpt-multi-users': report.multiaccount_flags ? formatNumber(report.multiaccount_flags.flagged_users) : 'n/a',
        };

        for (var rkey in reportMapping) {
            var rel = document.getElementById(rkey);
            if (rel) rel.textContent = reportMapping[rkey];
        }
    }

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function formatTimestamp(ts) {
        if (!ts) return 'n/a';
        var d = new Date(ts * 1000);
        var now = new Date();
        var diff = Math.floor((now - d) / 60000);
        if (diff < 1) return 'Gerade eben';
        if (diff < 60) return diff + ' Min';
        if (diff < 1440) return Math.floor(diff / 60) + ' Std';
        return Math.floor(diff / 1440) + ' Tage';
    }

    // ---- Chart.js Charts ----
    var charts = {};
    var chartColors = {
        activity: { border: '#38bdf8', bg: 'rgba(56,189,248,0.1)' },
        registrations: { border: '#22d3ee', bg: 'rgba(34,211,238,0.1)' },
        fleets: { border: '#a78bfa', bg: 'rgba(167,139,250,0.1)' },
        combats: { border: '#f472b6', bg: 'rgba(244,114,182,0.1)' }
    };

    function initCharts(chartData) {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not loaded');
            return;
        }

        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";
        Chart.defaults.font.size = 11;

        var chartConfigs = {
            activity: { el: 'chartActivity', label: 'Aktivitaet', data: chartData.activity },
            registrations: { el: 'chartRegistrations', label: 'Registrierungen', data: chartData.registrations },
            fleets: { el: 'chartFleets', label: 'Flottenstarts', data: chartData.fleets },
            combats: { el: 'chartCombats', label: 'Kaempfe', data: chartData.combats }
        };

        for (var key in chartConfigs) {
            createOrUpdateChart(key, chartConfigs[key]);
        }
    }

    function createOrUpdateChart(key, config) {
        var canvas = document.getElementById(config.el);
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        var color = chartColors[key];
        var data = config.data || { labels: [], values: [] };

        if (charts[key]) {
            charts[key].data.labels = data.labels || [];
            charts[key].data.datasets[0].data = data.values || [];
            charts[key].update('none');
            return;
        }

        charts[key] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: config.label,
                    data: data.values || [],
                    borderColor: color.border,
                    backgroundColor: color.bg,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: color.border,
                    pointHoverBackgroundColor: '#fff',
                    pointBorderColor: 'transparent',
                    pointHoverBorderColor: color.border,
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.95)',
                        borderColor: 'rgba(56,189,248,0.2)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        titleColor: '#e2e8f0',
                        bodyColor: '#94a3b8'
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                        ticks: { maxRotation: 45, maxTicksLimit: 12 }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    function updateCharts(chartData) {
        if (!chartData) return;
        initCharts(chartData);
    }

    // ---- Global Search ----
    var searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        var debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                var q = searchInput.value.trim();
                if (q.length >= 2) {
                    window.location.href = 'admin.php?page=search&search=users&searchtext=' + encodeURIComponent(q);
                }
            }, 600);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                var q = searchInput.value.trim();
                if (q.length >= 1) {
                    window.location.href = 'admin.php?page=search&search=users&searchtext=' + encodeURIComponent(q);
                }
            }
        });
    }

    // ---- Initialize ----
    // Charts are initialized from inline data on page load
    if (typeof window.adminDashboardData !== 'undefined') {
        var data = window.adminDashboardData;
        if (data.charts) {
            // Wait for Chart.js to load
            function tryInitCharts() {
                if (typeof Chart !== 'undefined') {
                    initCharts(data.charts);
                } else {
                    setTimeout(tryInitCharts, 100);
                }
            }
            tryInitCharts();
        }
    }

})();
