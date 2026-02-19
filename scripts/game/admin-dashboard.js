/**
 * SmartMoons Admin Dashboard JS - SM-3.1.0 (FIXED)
 * Handles: Sidebar toggle, Period tabs, Flipcards, Charts
 *
 * Fixes:
 * - Supports BOTH chartData formats:
 *   A) NEW: { labels:[], activity:[], registrations:[], fleets:[], combats:[], bots:[] }
 *   B) OLD: { activity:{labels:[],values:[]}, registrations:{labels:[],values:[]}, ... }
 * - Prevents overwriting already-created charts (e.g. inline Twig charts)
 * - Period tabs use redirect (no broken AJAX that expects JSON)
 */

(function () {
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
  document.querySelectorAll('.sidebar-link').forEach(function (link) {
    link.addEventListener('click', function () {
      if (window.innerWidth <= 768) closeSidebar();
    });
  });

  // ---- Flipcard Touch Toggle ----
  document.querySelectorAll('.flipcard').forEach(function (card) {
    card.addEventListener('click', function () {
      if (window.matchMedia('(hover: none)').matches) {
        this.classList.toggle('flipped');
      }
    });
  });

  // ---- Period Tabs (REDIRECT, not AJAX) ----
  const periodTabs = document.querySelectorAll('.period-tab');

  function buildPeriodUrl(period) {
    try {
      const url = new URL(window.location.href);
      const params = url.searchParams;
      params.set('period', period);

      // make sure page stays on overview
      if (!params.get('page')) params.set('page', 'overview');
      if (params.get('page') === '') params.set('page', 'overview');

      url.search = params.toString();
      return url.toString();
    } catch (e) {
      // fallback
      return 'admin.php?page=overview&period=' + encodeURIComponent(period);
    }
  }

  periodTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      const period = this.getAttribute('data-period');
      window.location.href = buildPeriodUrl(period);
    });
  });

  function formatNumber(n) {
    if (n === null || n === undefined || n === -1) return 'n/a';
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
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
      'kpi-bots': report.bot_activity ? formatNumber(report.bot_activity.active_last_15m) : 'n/a',
    };

    for (var key in mapping) {
      var el = document.getElementById(key);
      if (el) el.textContent = mapping[key];
    }

    // Top players
    var topList = document.getElementById('top-players-list');
    if (topList && Array.isArray(report.top_players)) {
      var html = '';
      report.top_players.forEach(function (p, i) {
        var rankClass = i === 0 ? 'gold' : (i === 1 ? 'silver' : (i === 2 ? 'bronze' : ''));
        html += '<div class="top-player-item">';
        html += '<div class="top-player-rank ' + rankClass + '">' + (i + 1) + '</div>';
        html += '<div class="top-player-name">' + escapeHtml(p.username || 'Unknown') + '</div>';
        html += '<div class="top-player-points">' + formatNumber(parseInt(p.points || 0)) + ' Pkt</div>';
        html += '</div>';
      });
      topList.innerHTML = html;
    }

    // Active players
    var activeList = document.getElementById('active-players-list');
    if (activeList && Array.isArray(report.active_players)) {
      var html2 = '';
      report.active_players.forEach(function (p, i) {
        var rankClass = i === 0 ? 'gold' : (i === 1 ? 'silver' : (i === 2 ? 'bronze' : ''));
        html2 += '<div class="top-player-item">';
        html2 += '<div class="top-player-rank ' + rankClass + '">' + (i + 1) + '</div>';
        html2 += '<div class="top-player-name">' + escapeHtml(p.username || 'Unknown') + '</div>';
        html2 += '<div class="top-player-points">' + formatTimestamp(p.onlinetime) + '</div>';
        html2 += '</div>';
      });
      activeList.innerHTML = html2;
    }

    // Report values
    var reportMapping = {
      'rpt-online-peak': report.players_online ? report.players_online.online : 'n/a',
      'rpt-new-players': report.registrations ? formatNumber(report.registrations.count) : 'n/a',
      'rpt-fleets-sent': report.fleets_sent ? formatNumber(report.fleets_sent.count) : 'n/a',
      'rpt-combats': report.combats ? formatNumber(report.combats.count) : 'n/a',
      'rpt-messages': report.messages ? formatNumber(report.messages.count) : 'n/a',
      'rpt-alliances': report.alliances_founded ? formatNumber(report.alliances_founded.count) : 'n/a',
      'rpt-multi-ips': report.multiaccount_flags ? formatNumber(report.multiaccount_flags.flagged_ips) : 'n/a',
      'rpt-multi-users': report.multiaccount_flags ? formatNumber(report.multiaccount_flags.flagged_users) : 'n/a',
      'rpt-bots-active': report.bot_activity ? formatNumber(report.bot_activity.active_last_15m) : 'n/a',
      'rpt-bots-last': report.bot_activity ? (report.bot_activity.last_tick_human || 'n/a') : 'n/a',
    };

    for (var rkey in reportMapping) {
      var rel = document.getElementById(rkey);
      if (rel) rel.textContent = reportMapping[rkey];
    }
  }

  // ---- Chart.js Charts ----
  var charts = {};
  var chartColors = {
    activity: { border: '#38bdf8', bg: 'rgba(56,189,248,0.10)' },
    registrations: { border: '#34d399', bg: 'rgba(52,211,153,0.12)' },
    fleets: { border: '#a78bfa', bg: 'rgba(167,139,250,0.12)' },
    combats: { border: '#f87171', bg: 'rgba(248,113,113,0.12)' },
    bots: { border: '#2dd4bf', bg: 'rgba(45,212,191,0.12)' }
  };

  function isArr(v) { return Array.isArray(v); }

  // Extract labels + values from BOTH formats
  function extractSeries(chartData, key) {
    if (!chartData) return { labels: [], values: [] };

    // NEW format: global labels + arrays per key
    if (isArr(chartData.labels) && isArr(chartData[key])) {
      return { labels: chartData.labels, values: chartData[key] };
    }

    // OLD format: chartData[key] = {labels:[], values:[]}
    if (chartData[key] && typeof chartData[key] === 'object') {
      var o = chartData[key];
      if (isArr(o.labels) && isArr(o.values)) {
        return { labels: o.labels, values: o.values };
      }
    }

    // Sometimes PHP json encodes associative arrays -> objects
    // Try converting object->array
    if (chartData.labels && typeof chartData.labels === 'object' && !isArr(chartData.labels)) {
      var lbls = Object.values(chartData.labels);
      var valsObj = chartData[key];
      var vals = (valsObj && typeof valsObj === 'object' && !isArr(valsObj)) ? Object.values(valsObj) : [];
      return { labels: lbls, values: vals };
    }

    return { labels: [], values: [] };
  }

  function normalizeLen(labels, values) {
    labels = isArr(labels) ? labels : [];
    values = isArr(values) ? values : [];

    var L = labels.length;
    if (values.length > L) values = values.slice(0, L);
    while (values.length < L) values.push(0);

    return { labels: labels, values: values };
  }

  function ensureChartJsDefaults() {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
  }

  function getExistingChartForCanvas(canvas) {
    try {
      if (typeof Chart !== 'undefined' && typeof Chart.getChart === 'function') {
        return Chart.getChart(canvas);
      }
    } catch (e) {}
    return null;
  }

  function createOrUpdateChart(key, elId, label, type, chartData) {
    var canvas = document.getElementById(elId);
    if (!canvas) return;

    var series = extractSeries(chartData, key);
    series = normalizeLen(series.labels, series.values);

    // If another script already created the chart, UPDATE it instead of overwriting
    var existing = getExistingChartForCanvas(canvas);
    if (existing) {
      existing.data.labels = series.labels;
      if (existing.data.datasets && existing.data.datasets[0]) {
        existing.data.datasets[0].data = series.values;
      }
      existing.update('none');
      charts[key] = existing;
      return;
    }

    // If we created before, update
    if (charts[key]) {
      charts[key].data.labels = series.labels;
      charts[key].data.datasets[0].data = series.values;
      charts[key].update('none');
      return;
    }

    var ctx = canvas.getContext('2d');
    var color = chartColors[key] || { border: '#38bdf8', bg: 'rgba(56,189,248,0.10)' };

    charts[key] = new Chart(ctx, {
      type: type,
      data: {
        labels: series.labels,
        datasets: [{
          label: label,
          data: series.values,
          borderColor: color.border,
          backgroundColor: color.bg,
          borderWidth: 2,
          fill: (type === 'line'),
          tension: 0.4,
          pointRadius: (type === 'line') ? 2 : 0,
          pointHoverRadius: (type === 'line') ? 5 : 0,
          borderRadius: (type === 'bar') ? 4 : 0
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

  function initCharts(chartData) {
    if (typeof Chart === 'undefined') return;
    ensureChartJsDefaults();

    // Use sensible chart types
    createOrUpdateChart('activity', 'chartActivity', 'Aktivität', 'line', chartData);
    createOrUpdateChart('registrations', 'chartRegistrations', 'Registrierungen', 'bar', chartData);
    createOrUpdateChart('fleets', 'chartFleets', 'Flottenstarts', 'line', chartData);
    createOrUpdateChart('combats', 'chartCombats', 'Kämpfe', 'bar', chartData);

    // NEW: bots
    createOrUpdateChart('bots', 'chartBots', 'Bot-Ticks', 'bar', chartData);
  }

  // ---- Global Search ----
  var searchInput = document.getElementById('globalSearch');
  if (searchInput) {
    var debounceTimer;
    searchInput.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        var q = searchInput.value.trim();
        if (q.length >= 2) {
          window.location.href = 'admin.php?page=search&search=users&searchtext=' + encodeURIComponent(q);
        }
      }, 600);
    });

    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        var q = searchInput.value.trim();
        if (q.length >= 1) {
          window.location.href = 'admin.php?page=search&search=users&searchtext=' + encodeURIComponent(q);
        }
      }
    });
  }

  // ---- Initialize from inline data ----
  function boot() {
    if (typeof window.adminDashboardData === 'undefined') return;
    var data = window.adminDashboardData || {};

    // Update KPIs if present
    if (data.report) updateKPIs(data.report);

    // Init charts (supports both formats)
    if (data.charts) {
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

  // Run after DOM is available
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

})();