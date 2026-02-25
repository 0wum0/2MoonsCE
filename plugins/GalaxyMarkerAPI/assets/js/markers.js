/**
 * GalaxyMarkerAPI – markers.js
 *
 * Renders an overlay layer on top of the existing Galaxy Map (Three.js canvas).
 * Does NOT modify or replace the Three.js scene.
 * Uses a 2D HTML overlay div positioned absolutely over the canvas.
 *
 * Data source: window.GalaxyMarkerData (JSON array injected by plugin.php)
 * Each marker: { galaxy, system, position, type, icon, color, tooltip }
 *
 * Coordinate mapping:
 *   The galaxy map canvas covers systems 1–499 horizontally and positions 1–15
 *   vertically within each system column. We map these to percentage-based
 *   positions on the overlay div so the markers are resolution-independent.
 */
(function () {
    'use strict';

    var SYSTEMS   = 499;
    var POSITIONS = 15;

    /**
     * Find the galaxy map canvas element.
     * The core galaxy map renders into a <canvas> inside #galaxy-map or .galaxy-map-wrap.
     */
    function findCanvas() {
        var wrap = document.getElementById('galaxy-map')
                || document.querySelector('.galaxy-map-wrap')
                || document.querySelector('.galaxy-canvas-wrap');
        if (wrap) {
            return wrap.querySelector('canvas') || wrap;
        }
        return document.querySelector('canvas[data-galaxy-map]')
            || document.querySelector('#galaxy-three-canvas');
    }

    /**
     * Build or retrieve the overlay container placed over the canvas.
     */
    function getOrCreateOverlay(canvas) {
        var existing = document.getElementById('gm-overlay');
        if (existing) {
            return existing;
        }

        var parent = canvas.parentElement;
        if (!parent) {
            return null;
        }

        // Ensure parent is positioned so absolute children work correctly.
        var parentPos = window.getComputedStyle(parent).position;
        if (parentPos === 'static') {
            parent.style.position = 'relative';
        }

        var overlay = document.createElement('div');
        overlay.id        = 'gm-overlay';
        overlay.className = 'gm-overlay';
        parent.appendChild(overlay);
        return overlay;
    }

    /**
     * Convert galaxy-map coordinates to overlay percentage position.
     * Only renders markers that match the currently viewed galaxy/system range.
     *
     * @param {number} system    1–499
     * @param {number} position  1–15
     * @returns {{ left: string, top: string }}
     */
    function toPercent(system, position) {
        var left = ((system - 1) / (SYSTEMS - 1) * 100).toFixed(3) + '%';
        var top  = ((position - 1) / (POSITIONS - 1) * 100).toFixed(3) + '%';
        return { left: left, top: top };
    }

    /**
     * Create a single DOM marker element.
     * @param {Object} marker
     * @returns {HTMLElement}
     */
    function createMarkerEl(marker) {
        var coords = toPercent(
            parseInt(marker.system,   10) || 1,
            parseInt(marker.position, 10) || 1
        );

        var wrap = document.createElement('div');
        wrap.className = 'gm-marker gm-marker--' + (marker.type || 'info');
        wrap.style.left = coords.left;
        wrap.style.top  = coords.top;

        var icon = document.createElement('div');
        icon.className = 'gm-marker__icon';
        icon.style.color = marker.color || '#38bdf8';

        var iconEl = document.createElement('i');
        iconEl.className = 'fas ' + (marker.icon || 'fa-map-marker-alt');
        icon.appendChild(iconEl);
        wrap.appendChild(icon);

        if (marker.tooltip) {
            var tip = document.createElement('div');
            tip.className   = 'gm-marker__tooltip';
            tip.textContent = marker.tooltip;
            wrap.appendChild(tip);
        }

        return wrap;
    }

    /**
     * Render all markers for the currently viewed galaxy.
     * Clears and re-renders on galaxy navigation.
     *
     * @param {HTMLElement} overlay
     * @param {Array}       markers
     * @param {number}      currentGalaxy
     */
    function renderMarkers(overlay, markers, currentGalaxy) {
        overlay.innerHTML = '';

        if (!Array.isArray(markers) || markers.length === 0) {
            return;
        }

        markers.forEach(function (m) {
            if (parseInt(m.galaxy, 10) !== currentGalaxy) {
                return;
            }
            var el = createMarkerEl(m);
            overlay.appendChild(el);
        });
    }

    /**
     * Detect the currently displayed galaxy from the DOM.
     * Falls back to 1 if not detectable.
     */
    function currentGalaxy() {
        var input = document.getElementById('galaxy')
                 || document.querySelector('input[name="galaxy"]');
        if (input) {
            return parseInt(input.value, 10) || 1;
        }
        return 1;
    }

    /**
     * Main init: wait for the canvas to be present, then attach overlay.
     */
    function init() {
        var markers = window.GalaxyMarkerData;
        if (!Array.isArray(markers) || markers.length === 0) {
            return;
        }

        var canvas = findCanvas();
        if (!canvas) {
            // Retry after a short delay (Three.js canvas may not be ready yet)
            setTimeout(init, 500);
            return;
        }

        var overlay = getOrCreateOverlay(canvas);
        if (!overlay) {
            return;
        }

        // Initial render
        renderMarkers(overlay, markers, currentGalaxy());

        // Re-render when the galaxy navigation changes.
        // Listen for the custom event fired by the galaxy map JS, or fall back
        // to observing the galaxy input value.
        document.addEventListener('galaxy.navigate', function (e) {
            var g = (e && e.detail && e.detail.galaxy) ? e.detail.galaxy : currentGalaxy();
            renderMarkers(overlay, markers, g);
        });

        // Fallback: observe galaxy input changes
        var galaxyInput = document.getElementById('galaxy')
                       || document.querySelector('input[name="galaxy"]');
        if (galaxyInput) {
            galaxyInput.addEventListener('change', function () {
                renderMarkers(overlay, markers, currentGalaxy());
            });
        }

        // Re-render on canvas resize
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(function () {
                renderMarkers(overlay, markers, currentGalaxy());
            }).observe(canvas);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose public API for other scripts
    window.GalaxyMarkerAPI = {
        refresh: function () {
            var canvas  = findCanvas();
            var overlay = canvas ? getOrCreateOverlay(canvas) : null;
            if (overlay) {
                renderMarkers(overlay, window.GalaxyMarkerData || [], currentGalaxy());
            }
        },
        push: function (markerData) {
            if (!Array.isArray(window.GalaxyMarkerData)) {
                window.GalaxyMarkerData = [];
            }
            window.GalaxyMarkerData.push(markerData);
            window.GalaxyMarkerAPI.refresh();
        }
    };
})();
