/**
 * GalacticEvents – events.js
 *
 * Responsibilities:
 *  1. Live countdown on .ge-countdown[data-until] elements (updates every second).
 *  2. Poll game.php?page=galactic_events_api&mode=status every 60 s to detect
 *     new events that started after page load (AJAX live-update, no page reload).
 *  3. Show a toast notification when a new event is detected.
 *  4. Inject / update the sidebar banner when a new event starts.
 *  5. Remove banner + reset when event expires.
 */

(function () {
    'use strict';

    // ── Config ────────────────────────────────────────────────────────────────
    var POLL_INTERVAL_MS = 60000;   // how often to check for new events
    var API_URL          = 'game.php?page=galactic_events_api&mode=status';
    var BANNER_ID        = 'ge-sidebar-banner';
    var TOAST_DURATION   = 8000;    // ms

    // ── State ─────────────────────────────────────────────────────────────────
    var countdownTimer = null;
    var pollTimer      = null;
    var lastEventId    = 0;         // track last seen event id to detect new ones

    // ── Countdown engine ──────────────────────────────────────────────────────

    function updateCountdowns() {
        var now  = Math.floor(Date.now() / 1000);
        var elms = document.querySelectorAll('.ge-countdown[data-until]');

        for (var i = 0; i < elms.length; i++) {
            var el    = elms[i];
            var until = parseInt(el.getAttribute('data-until'), 10);
            var secs  = Math.max(0, until - now);

            el.textContent = formatSeconds(secs);

            if (secs < 300) {
                el.classList.add('ge-countdown--urgent');
            } else {
                el.classList.remove('ge-countdown--urgent');
            }

            if (secs === 0) {
                // Event expired – hide banner after short delay
                var banner = document.getElementById(BANNER_ID);
                if (banner) {
                    setTimeout(function () {
                        if (banner && banner.parentNode) {
                            banner.style.transition = 'opacity 0.5s';
                            banner.style.opacity    = '0';
                            setTimeout(function () {
                                if (banner && banner.parentNode) {
                                    banner.parentNode.removeChild(banner);
                                }
                            }, 500);
                        }
                    }, 2000);
                }
            }
        }
    }

    function formatSeconds(total) {
        var h = Math.floor(total / 3600);
        var m = Math.floor((total % 3600) / 60);
        var s = total % 60;
        return pad(h) + ':' + pad(m) + ':' + pad(s);
    }

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    // ── Banner inject / update ────────────────────────────────────────────────

    function buildBannerHtml(event) {
        var value   = parseFloat(event.effect_value);
        var sign    = value >= 0 ? '+' : '';
        var isMalus = value < 0;
        var name    = escHtml(event.name);
        var type    = escHtml(event.effect_type);
        var until   = parseInt(event.active_until, 10);
        var secs    = Math.max(0, until - Math.floor(Date.now() / 1000));
        var malus   = isMalus ? ' ge-banner--malus' : '';

        return '<div class="ge-banner' + malus + '" id="' + BANNER_ID + '" data-until="' + until + '">'
            + '<div class="ge-banner__icon">&#x1F30C;</div>'
            + '<div class="ge-banner__body">'
            + '<div class="ge-banner__title">' + name + '</div>'
            + '<div class="ge-banner__effect">' + sign + value + '% <span class="ge-banner__type">' + type + '</span></div>'
            + '<div class="ge-banner__timer">'
            + '<span class="ge-banner__timer-label">Endet in:</span>'
            + '<span class="ge-countdown" id="ge-countdown" data-until="' + until + '">' + formatSeconds(secs) + '</span>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function injectOrUpdateBanner(event) {
        var existing = document.getElementById(BANNER_ID);
        var html     = buildBannerHtml(event);

        if (existing) {
            // Replace in-place to preserve DOM position
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            var newEl = tmp.firstChild;
            existing.parentNode.replaceChild(newEl, existing);
        } else {
            // Try to append after sidebar navigation end
            var sidebar = document.querySelector('.sidebar-footer, .sidebar, #sidebar, nav');
            if (sidebar) {
                var tmp2 = document.createElement('div');
                tmp2.innerHTML = html;
                sidebar.parentNode.insertBefore(tmp2.firstChild, sidebar.nextSibling);
            }
        }
    }

    // ── Toast notifications ───────────────────────────────────────────────────

    function showToast(event) {
        var value  = parseFloat(event.effect_value);
        var sign   = value >= 0 ? '+' : '';
        var isMal  = value < 0;
        var name   = escHtml(event.name);
        var type   = escHtml(event.effect_type);
        var cls    = isMal ? ' ge-toast--malus' : '';

        var toast = document.createElement('div');
        toast.className = 'ge-toast' + cls;
        toast.innerHTML = '<div class="ge-toast__title">&#x1F30C; Galaktisches Event!</div>'
            + '<div class="ge-toast__body">'
            + '<strong>' + name + '</strong><br>'
            + sign + value + '% ' + type
            + '</div>';

        document.body.appendChild(toast);

        setTimeout(function () {
            toast.style.animation = 'ge-toastout 0.35s ease-in forwards';
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 350);
        }, TOAST_DURATION);
    }

    // ── API polling ───────────────────────────────────────────────────────────

    function pollEventStatus() {
        if (typeof fetch === 'undefined') {
            return; // Very old browser fallback – skip polling
        }

        fetch(API_URL, {
            method:      'GET',
            credentials: 'same-origin',
            headers:     { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) {
            if (!r.ok) { return null; }
            return r.json();
        })
        .then(function (data) {
            if (!data || !data.ok) { return; }

            if (data.event === null || data.event === undefined) {
                // No active event – ensure banner is removed if it's still showing
                var banner = document.getElementById(BANNER_ID);
                if (banner && banner.parentNode) {
                    banner.parentNode.removeChild(banner);
                }
                lastEventId = 0;
                return;
            }

            var ev = data.event;
            var id = parseInt(ev.id, 10);

            if (id !== lastEventId) {
                // New event detected
                lastEventId = id;
                injectOrUpdateBanner(ev);
                showToast(ev);
            } else {
                // Same event, just update the data-until in case page was stale
                var el = document.getElementById(BANNER_ID);
                if (!el) {
                    injectOrUpdateBanner(ev);
                }
            }
        })
        .catch(function (err) {
            // Silent – don't spam console on network errors
        });
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    function init() {
        // Detect existing event id from banner if server rendered it
        var banner = document.getElementById(BANNER_ID);
        if (banner) {
            var until = parseInt(banner.getAttribute('data-until') || '0', 10);
            if (until > Math.floor(Date.now() / 1000)) {
                // Mark as known so we don't re-toast on first poll
                // We don't have the id here, but 0 means "any new id triggers toast"
                // Use a sentinel so existing banner doesn't get re-toasted:
                lastEventId = -1;
            }
        }

        // Start countdown tick
        updateCountdowns();
        countdownTimer = setInterval(updateCountdowns, 1000);

        // Start API poll
        pollTimer = setInterval(pollEventStatus, POLL_INTERVAL_MS);

        // Initial poll after short delay
        setTimeout(pollEventStatus, 3000);
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    function escHtml(str) {
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose minimal public API for external use
    window.GalacticEvents = {
        refresh: pollEventStatus,
        formatSeconds: formatSeconds
    };

}());
