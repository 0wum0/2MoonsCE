/**
 * Live Fleet Tracker – Galaxy Map Integration
 * Hooks into the existing Three.js galaxy map fleet rendering.
 * Adds: live polling, fleet dot tooltips, interception modal, NPC/warp alerts.
 */
(function () {
    'use strict';

    const CFG = window.LFT_Config || { pollInterval: 3000, interceptionEnabled: false, assetBase: './plugins/LiveFleetTracker/assets/' };
    const POLL_MS  = Math.max(1000, CFG.pollInterval || 3000);
    const BASE_URL = 'game.php';

    // ── SVG Icon helpers ──────────────────────────────────────────────────────

    const SVG = {
        skull: `<svg viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2a7 7 0 0 1 7 7c0 2.38-1.19 4.47-3 5.74V17a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1v-2.26C6.19 13.47 5 11.38 5 9a7 7 0 0 1 7-7z"/>
            <line x1="9" y1="22" x2="9" y2="17"/><line x1="15" y1="22" x2="15" y2="17"/>
            <line x1="10" y1="12" x2="10" y2="12"/><line x1="14" y1="12" x2="14" y2="12"/>
        </svg>`,
        warp: `<svg viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
        </svg>`,
        crosshair: `<svg viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/>
            <line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/>
            <line x1="12" y1="22" x2="12" y2="18"/>
        </svg>`,
        rocket: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/>
            <path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/>
            <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>
        </svg>`,
        shield: `<svg viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>`,
        warning: `<svg viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>`,
    };

    const MISSION_LABELS = {
        ATTACK: 'Angriff', ACS_ATTACK: 'AKS-Angriff', TRANSPORT: 'Transport',
        DEPLOY: 'Stationierung', HOLD: 'Halten', ESPIONAGE: 'Spionage',
        COLONY: 'Kolonie', RECYCLE: 'Recycling', DESTROY: 'Zerstörung',
        EXPEDITION: 'Expedition', UNKNOWN: 'Unbekannt',
    };

    // ── DOM Setup ─────────────────────────────────────────────────────────────

    function buildDOM() {
        // Tooltip
        if (!document.getElementById('lft-tooltip')) {
            const tt = document.createElement('div');
            tt.id = 'lft-tooltip';
            tt.innerHTML = `
                <div class="lft-tt-header" id="lft-tt-header"></div>
                <div class="lft-tt-progress"><div class="lft-tt-progress-bar" id="lft-tt-bar"></div></div>
                <div id="lft-tt-rows"></div>
                <button class="lft-tt-intercept-btn" id="lft-tt-intercept" style="display:none;">
                    ${SVG.crosshair} Flotte abfangen
                </button>`;
            document.body.appendChild(tt);
        }

        // Interception modal
        if (!document.getElementById('lft-intercept-modal')) {
            const modal = document.createElement('div');
            modal.id = 'lft-intercept-modal';
            modal.innerHTML = `
                <div class="lft-modal-box">
                    <button class="lft-modal-close" id="lft-modal-close">✕</button>
                    <div class="lft-modal-title">${SVG.crosshair} Flotte abfangen</div>
                    <div class="lft-modal-fleet-info" id="lft-modal-fleet-info"></div>
                    <div class="lft-modal-body">
                        Bist du sicher? Deine schnellsten verfügbaren Schiffe werden auf Abfangkurs geschickt.
                        Ein Abfang-Angriff gilt als Kampfmission.
                    </div>
                    <div class="lft-modal-actions">
                        <button class="lft-btn-cancel-modal" id="lft-modal-cancel">Abbrechen</button>
                        <button class="lft-btn-confirm-intercept" id="lft-modal-confirm">
                            ${SVG.crosshair} Abfangen
                        </button>
                    </div>
                </div>`;
            document.body.appendChild(modal);
        }

        // Alert container
        if (!document.getElementById('lft-alerts')) {
            const alerts = document.createElement('div');
            alerts.id = 'lft-alerts';
            document.body.appendChild(alerts);
        }
    }

    // ── Tooltip ───────────────────────────────────────────────────────────────

    let _tooltipFleet = null;

    function showTooltip(fleet, x, y) {
        _tooltipFleet = fleet;
        const tt      = document.getElementById('lft-tooltip');
        const header  = document.getElementById('lft-tt-header');
        const bar     = document.getElementById('lft-tt-bar');
        const rows    = document.getElementById('lft-tt-rows');
        const btn     = document.getElementById('lft-tt-intercept');
        if (!tt) return;

        const mLabel = MISSION_LABELS[fleet.mission_name] || fleet.mission_name;
        const pct    = Math.round(fleet.progress * 100);
        const remaining = fleet.remaining || 0;
        const h = Math.floor(remaining / 3600);
        const m = Math.floor((remaining % 3600) / 60);
        const s = remaining % 60;
        const etaStr = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;

        // Icon based on type
        let icon = SVG.rocket;
        let iconColor = fleet.color || '#aabbcc';
        if (fleet.is_npc) icon = SVG.skull;

        header.innerHTML = `<span style="color:${iconColor}">${icon}</span>
            <span style="color:${iconColor}">${fleet.is_npc ? 'Piraten-NPC' : (fleet.owner_name || '???')}</span>`;

        bar.style.background = iconColor;
        bar.style.width = pct + '%';

        const start = fleet.start || {};
        const end   = fleet.end   || {};
        rows.innerHTML = `
            <div class="lft-tt-row"><span class="lft-tt-label">Mission</span><span class="lft-tt-val">${mLabel}</span></div>
            <div class="lft-tt-row"><span class="lft-tt-label">Von</span><span class="lft-tt-val">[${start.g||'?'}:${start.s||'?'}:${start.p||'?'}]</span></div>
            <div class="lft-tt-row"><span class="lft-tt-label">Nach</span><span class="lft-tt-val">[${end.g||'?'}:${end.s||'?'}:${end.p||'?'}]</span></div>
            <div class="lft-tt-row"><span class="lft-tt-label">Fortschritt</span><span class="lft-tt-val">${pct}%</span></div>
            <div class="lft-tt-row"><span class="lft-tt-label">Ankunft in</span><span class="lft-tt-val" id="lft-tt-eta">${etaStr}</span></div>
            ${fleet.is_warp_delayed ? `<div class="lft-tt-row"><span class="lft-tt-label" style="color:#fbbf24">${SVG.warning} Warp-Störung</span><span class="lft-tt-val" style="color:#fbbf24">Verzögert</span></div>` : ''}
            ${fleet.npc_attack ? `<div class="lft-tt-row"><span class="lft-tt-label" style="color:#f87171">${SVG.skull} Piraten-Angriff</span><span class="lft-tt-val" style="color:#f87171">-${fleet.npc_attack.loss_percent}%</span></div>` : ''}`;

        // Show intercept button for all non-own, non-NPC, non-ally foreign fleets
        if (CFG.interceptionEnabled && !fleet.is_own && !fleet.is_npc && !fleet.is_ally) {
            btn.style.display = 'flex';
            btn.onclick = () => openInterceptModal(fleet);
        } else {
            btn.style.display = 'none';
        }

        tt.classList.add('visible');
        positionTooltip(tt, x, y);
    }

    function positionTooltip(tt, x, y) {
        const pad = 16;
        let left = x + pad;
        let top  = y + pad;
        if (left + 300 > window.innerWidth)  left = x - 300 - pad;
        if (top  + 250 > window.innerHeight) top  = y - 250 - pad;
        tt.style.left = left + 'px';
        tt.style.top  = top  + 'px';
    }

    function hideTooltip() {
        const tt = document.getElementById('lft-tooltip');
        if (tt) tt.classList.remove('visible');
        _tooltipFleet = null;
    }

    // Live countdown inside tooltip
    setInterval(function () {
        if (!_tooltipFleet) return;
        const etaEl = document.getElementById('lft-tt-eta');
        if (!etaEl) return;
        _tooltipFleet.remaining = Math.max(0, (_tooltipFleet.remaining || 0) - 1);
        const r = _tooltipFleet.remaining;
        const h = Math.floor(r / 3600);
        const m = Math.floor((r % 3600) / 60);
        const s = r % 60;
        etaEl.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    }, 1000);

    // ── Interception Modal ────────────────────────────────────────────────────

    let _pendingInterceptFleet = null;

    function openInterceptModal(fleet) {
        _pendingInterceptFleet = fleet;
        const modal = document.getElementById('lft-intercept-modal');
        const info  = document.getElementById('lft-modal-fleet-info');
        if (!modal || !info) return;

        const mLabel = MISSION_LABELS[fleet.mission_name] || fleet.mission_name;
        info.innerHTML = `
            <div class="row"><span class="key">Flotte</span><span class="value">#${fleet.id}</span></div>
            <div class="row"><span class="key">Kommandant</span><span class="value">${fleet.owner_name || '???'}</span></div>
            <div class="row"><span class="key">Mission</span><span class="value">${mLabel}</span></div>
            <div class="row"><span class="key">Ziel</span><span class="value">[${(fleet.end||{}).g}:${(fleet.end||{}).s}:${(fleet.end||{}).p}]</span></div>`;

        modal.classList.add('open');
        hideTooltip();
    }

    function closeInterceptModal() {
        const modal = document.getElementById('lft-intercept-modal');
        if (modal) modal.classList.remove('open');
        _pendingInterceptFleet = null;
    }

    async function confirmIntercept() {
        if (!_pendingInterceptFleet) return;
        const btn = document.getElementById('lft-modal-confirm');
        if (btn) btn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'intercept');
            fd.append('target_fleet_id', _pendingInterceptFleet.id);

            const res  = await fetch(BASE_URL + '?page=lft_intercept', { method: 'POST', body: fd });
            const data = await res.json();

            closeInterceptModal();

            if (data.ok) {
                showAlert('intercept', 'Abfang-Befehl gesendet', data.message || 'Deine Flotte ist auf Abfangkurs.');
            } else {
                const errMap = {
                    fleet_not_found:            'Flotte nicht mehr auffindbar.',
                    cannot_intercept_own_fleet:  'Du kannst deine eigene Flotte nicht abfangen.',
                    fleet_not_hostile:           'Diese Flotte ist keine Kampfflotte.',
                    already_intercepting:        'Diese Flotte wird bereits abgefangen.',
                    interception_disabled:       'Interception ist deaktiviert.',
                };
                showAlert('error', 'Abfang fehlgeschlagen', errMap[data.error] || data.error || 'Unbekannter Fehler.');
            }
        } catch (e) {
            closeInterceptModal();
            showAlert('error', 'Fehler', 'Verbindungsfehler beim Abfang-Befehl.');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    // ── Alert System ──────────────────────────────────────────────────────────

    function showAlert(type, title, msg) {
        const container = document.getElementById('lft-alerts');
        if (!container) return;

        const div = document.createElement('div');
        div.className = 'lft-alert' + (type === 'warp' ? ' warp' : '');

        let icon = SVG.skull;
        if (type === 'warp')      icon = SVG.warp;
        if (type === 'intercept') icon = SVG.crosshair;
        if (type === 'error')     icon = SVG.warning;

        div.innerHTML = `
            <div class="lft-alert-icon">${icon}</div>
            <div class="lft-alert-body">
                <div class="lft-alert-title">${title}</div>
                <div class="lft-alert-msg">${msg}</div>
            </div>
            <button class="lft-alert-close">✕</button>`;

        div.querySelector('.lft-alert-close').addEventListener('click', () => div.remove());
        container.appendChild(div);

        // Auto-dismiss after 8 seconds
        setTimeout(() => { if (div.parentNode) div.remove(); }, 8000);
    }

    // ── Alert Polling (NPC + Warp events) ────────────────────────────────────

    async function pollAlerts() {
        try {
            const res  = await fetch(BASE_URL + '?page=lft_intercept&action=alerts');
            const data = await res.json();
            if (!data.ok) return;

            (data.npc_attacks || []).forEach(function (a) {
                showAlert('npc',
                    '🏴‍☠️ Piraten-Angriff!',
                    `Flotte #${a.fleet_id} wurde angegriffen! Verlust: ${a.loss_percent}% bei [${a.galaxy}:${a.system}:${a.planet}]`
                );
            });

            (data.warp_events || []).forEach(function (w) {
                showAlert('warp',
                    '⚡ Warp-Störung!',
                    `Flotte #${w.fleet_id} wurde durch eine Warp-Anomalie um ${w.delay_seconds}s verzögert.`
                );
            });
        } catch (e) {
            // Silent fail – alerts are not critical
        }
    }

    // ── Galaxy Map 3D Integration ─────────────────────────────────────────────
    // Hooks into the existing window.GmFleets / buildFleets() from the galaxy map template.
    // We intercept the fleet data fetch and inject our enhanced data.

    let _fleetData = [];
    let _fleetMap  = {}; // id → fleet
    let _warpDelayedIds = new Set();
    let _npcAttackMap   = {}; // fleet_id → npc_attack

    async function fetchFleets() {
        try {
            const res  = await fetch(BASE_URL + '?page=galaxyMap&mode=fleets');
            const data = await res.json();
            const fleets = data.fleets || [];

            // Merge LFT metadata (warp delays, npc attacks) into fleet objects
            fleets.forEach(function (f) {
                f.is_warp_delayed = _warpDelayedIds.has(f.id);
                f.npc_attack       = _npcAttackMap[f.id] || null;
                f.is_npc           = false; // real fleets only; NPC dots are separate
            });

            _fleetData = fleets;
            _fleetMap  = {};
            fleets.forEach(function (f) { _fleetMap[f.id] = f; });

            // Push updated fleet data into the galaxy map renderer
            if (window.GmFleets && typeof window.GmFleets.update === 'function') {
                window.GmFleets.update(fleets, data.server_time);
            }
            // Trigger Three.js fleet rebuild if available
            if (typeof window.lftTriggerFleetRebuild === 'function') {
                window.lftTriggerFleetRebuild(fleets, data.server_time);
            }

        } catch (e) {
            // Network error – keep existing data
        }
    }

    // ── Three.js dot interaction ──────────────────────────────────────────────
    // The galaxy map template exposes window.GmFleetDotPick(fleetId, screenX, screenY).
    // We register our handler here.

    window.LFT_onFleetHover = function (fleetId, x, y) {
        const f = _fleetMap[fleetId];
        if (f) {
            showTooltip(f, x, y);
        }
    };

    window.LFT_onFleetOut = function () {
        hideTooltip();
    };

    window.LFT_onFleetClick = function (fleetId, x, y) {
        const f = _fleetMap[fleetId];
        if (!f) return;
        if (CFG.interceptionEnabled && f.is_hostile && !f.is_own && !f.is_npc) {
            openInterceptModal(f);
        } else {
            showTooltip(f, x, y);
        }
    };

    // ── 2D Canvas Overlay (for non-3D mode) ──────────────────────────────────

    function init2DOverlay() {
        const mapEl = document.getElementById('vb-2d') || document.getElementById('gm-2d-container');
        if (!mapEl) return;

        let canvas = document.getElementById('lft-canvas-2d');
        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.id = 'lft-canvas-2d';
            canvas.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:5;';
            mapEl.style.position = 'relative';
            mapEl.appendChild(canvas);
        }

        function resize() {
            canvas.width  = mapEl.offsetWidth;
            canvas.height = mapEl.offsetHeight;
        }
        resize();
        window.addEventListener('resize', resize);

        // Draw fleet paths on 2D canvas
        function drawFleets() {
            const ctx = canvas.getContext('2d');
            if (!ctx) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Map galaxy coords to canvas (rough estimation for 2D grid)
            // The galaxy map 2D shows g/s/p in a grid – we draw approximate paths
            _fleetData.forEach(function (f) {
                const sx = ((f.start.s || 1) / 499) * canvas.width;
                const sy = ((f.start.g || 1) / 9)   * canvas.height;
                const ex = ((f.end.s || 1) / 499)   * canvas.width;
                const ey = ((f.end.g || 1) / 9)     * canvas.height;

                // Current position (interpolated)
                const cx = sx + (ex - sx) * f.progress;
                const cy = sy + (ey - sy) * f.progress;

                // Draw path line
                ctx.beginPath();
                ctx.moveTo(sx, sy);
                ctx.lineTo(ex, ey);
                ctx.strokeStyle = (f.color || '#aabbcc') + '33';
                ctx.lineWidth   = 1;
                ctx.setLineDash([6, 4]);
                ctx.stroke();
                ctx.setLineDash([]);

                // Draw fleet dot at current position
                const radius = f.is_own ? 5 : 4;
                ctx.beginPath();
                ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                ctx.fillStyle = f.color || '#aabbcc';
                ctx.fill();

                // Glow effect
                ctx.beginPath();
                ctx.arc(cx, cy, radius + 3, 0, Math.PI * 2);
                ctx.fillStyle = (f.color || '#aabbcc') + '44';
                ctx.fill();
            });
        }

        setInterval(drawFleets, Math.max(500, POLL_MS / 2));
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    function boot() {
        buildDOM();

        // Modal event listeners
        const closeBtn   = document.getElementById('lft-modal-close');
        const cancelBtn  = document.getElementById('lft-modal-cancel');
        const confirmBtn = document.getElementById('lft-modal-confirm');
        if (closeBtn)   closeBtn.addEventListener('click',   closeInterceptModal);
        if (cancelBtn)  cancelBtn.addEventListener('click',  closeInterceptModal);
        if (confirmBtn) confirmBtn.addEventListener('click', confirmIntercept);

        // Close modal on backdrop click
        const modal = document.getElementById('lft-intercept-modal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeInterceptModal();
            });
        }

        // Hide tooltip on map click (if not intercepting)
        document.addEventListener('click', function (e) {
            const tt = document.getElementById('lft-tooltip');
            if (tt && !tt.contains(e.target)) hideTooltip();
        });

        // Initial fetch + polling
        fetchFleets();
        setInterval(fetchFleets, POLL_MS);

        // Alert polling every 30 seconds
        pollAlerts();
        setInterval(pollAlerts, 30000);

        // 2D overlay (only if map is in 2D mode)
        init2DOverlay();

        console.log('[LFT] Live Fleet Tracker booted. Poll interval: ' + POLL_MS + 'ms');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
