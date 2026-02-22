// topnav.js — SmartMoons Resource Live-Ticker v2.0
// Verbesserungen:
//  - Progressbar-Farbverlauf dynamisch (0-30% rot, 30-70% gelb, 70-100% grün)
//  - Smooth Einfahren beim Laden via requestAnimationFrame
//  - Kein Double-Interval mehr
//  - Formatting nutzt bestehendes shortly_number / NumberGetHumanReadable

/**
 * Berechnet die Progressbar-Hintergrundfarbe dynamisch anhand des Füllstands.
 * 0–30% → rot-orange Gradient
 * 30–70% → gelb-orange Gradient
 * 70–100% → grün-cyan Gradient
 * @param {number} pct – Prozentwert 0–100
 * @returns {string} CSS linear-gradient string
 */
function resBarGradient(pct) {
    if (pct <= 30) {
        // Rot-Zone: 0-30% — von tiefrot nach orange
        var mix = pct / 30; // 0..1
        return 'linear-gradient(90deg, #ef4444, ' + lerpColor('#ef4444', '#f97316', mix) + ')';
    } else if (pct <= 70) {
        // Gelb-Zone: 30-70% — von orange nach gelb-grün
        var mix = (pct - 30) / 40; // 0..1
        return 'linear-gradient(90deg, ' + lerpColor('#f97316', '#eab308', mix) + ', ' + lerpColor('#eab308', '#84cc16', mix) + ')';
    } else {
        // Grün-Zone: 70-100% — von gelbgrün nach cyan
        var mix = (pct - 70) / 30; // 0..1
        return 'linear-gradient(90deg, ' + lerpColor('#84cc16', '#22d3ee', mix) + ', ' + lerpColor('#22d3ee', '#3b82f6', mix) + ')';
    }
}

/**
 * Lineares Farbmischen zwischen zwei Hex-Farben.
 */
function lerpColor(hexA, hexB, t) {
    function parse(h) {
        var r = parseInt(h.slice(1,3),16), g = parseInt(h.slice(3,5),16), b = parseInt(h.slice(5,7),16);
        return [r,g,b];
    }
    function toHex(n) { return ('0'+Math.round(n).toString(16)).slice(-2); }
    var a = parse(hexA), b = parse(hexB);
    return '#' + toHex(a[0]+(b[0]-a[0])*t) + toHex(a[1]+(b[1]-a[1])*t) + toHex(a[2]+(b[2]-a[2])*t);
}

/**
 * Setzt Progressbar-Breite smooth via requestAnimationFrame.
 * Animiert vom aktuellen Wert auf den Zielwert.
 */
function animateBar(fill, targetPct) {
    if (!fill) return;
    var current = parseFloat(fill.style.width) || 0;
    var target  = Math.min(100, Math.max(0, targetPct));
    var diff = target - current;
    if (Math.abs(diff) < 0.2) {
        fill.style.width = target + '%';
        return;
    }
    var step = diff * 0.12; // Easing
    fill.style.width = (current + step).toFixed(2) + '%';
    requestAnimationFrame(function() { animateBar(fill, target); });
}

/**
 * Haupt-Ticker: berechnet aktuellen Ressourcenwert, aktualisiert DOM + Progressbar.
 * @param {Object} config
 * @param {string} config.available    – Startmenge (Servertime)
 * @param {Array}  config.limit        – [min, max]
 * @param {number} config.production   – Produktion pro Stunde
 * @param {string} config.valueElem    – Element-ID für Zahlenwert
 * @param {string} [config.valuePoursent] – CSS-Klasse für altes Bar-System (Kompatibilität)
 * @param {boolean} init – wenn true, startet 1s-Interval
 */
function resourceTicker(config, init) {
    if (typeof init !== 'undefined' && init === true) {
        window.setInterval(function() { resourceTicker(config); }, 1000);
    }

    var element = document.getElementById(config.valueElem);
    if (!element) return;

    if (element.classList.contains('res_current_max')) return;

    var elapsed = (serverTime.getTime() - startTime) / 1000;
    var nrResource = Math.max(0, Math.floor(
        parseFloat(config.available) + parseFloat(config.production) / 3600 * elapsed
    ));
    var limit = parseFloat(config.limit[1]);

    // Wert-Anzeige
    if (nrResource < limit) {
        var pct = Math.max(0, (nrResource / limit) * 100).toFixed(1);

        if (!element.classList.contains('res_current_warn') && nrResource >= limit * 0.9) {
            element.classList.add('res_current_warn');
        }

        var display = viewShortlyNumber
            ? shortly_number(nrResource)
            : NumberGetHumanReadable(nrResource);

        element.innerHTML = display;

        // Alte Bar-Klasse (Kompatibilität topnav legacy)
        if (config.valuePoursent) {
            var legacy = document.querySelector('.' + config.valuePoursent);
            if (legacy) legacy.style.width = pct + '%';
        }

        // Neue res-progress-fill Progressbar
        _updateResBar(config.valueElem, parseFloat(pct));

    } else {
        element.classList.add('res_current_max');
        element.innerHTML = viewShortlyNumber
            ? shortly_number(nrResource)
            : NumberGetHumanReadable(nrResource);
        _updateResBar(config.valueElem, 100);
    }
}

/**
 * Findet die zugehörige .res-progress-fill und aktualisiert Breite + Farbe.
 * Sucht über: ID des Wert-Elements → Eltern-Container → .res-progress-fill
 */
function _updateResBar(valueElemId, pct) {
    var el = document.getElementById(valueElemId);
    if (!el) return;
    // Suche im nächsten .res-item Elternelement
    var resItem = el.closest('.res-item');
    if (!resItem) return;
    var fill = resItem.querySelector('.res-progress-fill');
    if (!fill) return;

    // Farbe dynamisch setzen
    fill.style.background = resBarGradient(pct);

    // Klassen für Animationen
    fill.classList.toggle('is-low',  pct <= 30);
    fill.classList.toggle('is-full', pct >= 100);

    // Smooth Animate
    animateBar(fill, pct);
}

/**
 * Einmaliges sanftes Einfahren aller Progressbars beim Seitenload.
 * Liest data-Attribute aus dem DOM die der Twig setzt.
 */
function initResProgressBars() {
    var items = document.querySelectorAll('.res-item[data-res-current]');
    items.forEach(function(item) {
        var current    = parseFloat(item.getAttribute('data-res-current')) || 0;
        var max        = parseFloat(item.getAttribute('data-res-max'))     || 1;
        var production = parseFloat(item.getAttribute('data-res-production')) || 0;
        var fill = item.querySelector('.res-progress-fill');
        if (!fill) return;

        // Initial bei 0 starten, dann einfahren
        fill.style.width = '0%';
        fill.style.transition = 'none';

        var pct = Math.min(100, Math.max(0, (current / max) * 100));
        fill.style.background = resBarGradient(pct);
        fill.classList.toggle('is-low', pct <= 30);
        fill.classList.toggle('is-full', pct >= 100);

        // Nach kurzem Delay smooth einfahren
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                fill.style.transition = 'width 1.2s cubic-bezier(0.4, 0, 0.2, 1), filter 0.3s ease';
                fill.style.width = pct.toFixed(2) + '%';
            });
        });
    });
}

// Einfahren bei DOM-Ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initResProgressBars);
} else {
    initResProgressBars();
}

/**
 * Legacy-Kompatibilität: getRessource bleibt unverändert.
 */
function getRessource(name) {
    var el = document.getElementById('current_' + name);
    if (!el) return 0;
    return parseInt(el.getAttribute('data-real') || el.textContent) || 0;
}