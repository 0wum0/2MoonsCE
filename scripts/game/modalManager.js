/**
 * ModalManager — Glass Modal System for SmartMoons / 2MoonsCE
 * Replaces Fancybox with a zero-dependency iframe modal.
 * Exposes: window.ModalManager.open(url, options), window.ModalManager.close()
 */
(function (global) {
    'use strict';

    var overlay, card, titleEl, closeBtn, body, iframe, loader;
    var _isOpen = false;

    /* ------------------------------------------------------------------ */
    /*  Self-inject modal.css into this document's <head> (once)           */
    /* ------------------------------------------------------------------ */
    (function _injectCSS() {
        if (global.document.getElementById('mm-css')) { return; }
        var link = global.document.createElement('link');
        link.id   = 'mm-css';
        link.rel  = 'stylesheet';
        link.type = 'text/css';
        link.href = 'styles/resource/css/game/modal.css';
        global.document.getElementsByTagName('head')[0].appendChild(link);
    }());

    /* ------------------------------------------------------------------ */
    /*  Build DOM (called once on first open or DOMContentLoaded)          */
    /* ------------------------------------------------------------------ */
    function _build() {
        if (document.getElementById('mm-overlay')) {
            _cache();
            return;
        }

        overlay = document.createElement('div');
        overlay.id = 'mm-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'mm-title');

        overlay.innerHTML = [
            '<div id="mm-card">',
            '  <div id="mm-header">',
            '    <span id="mm-title"></span>',
            '    <button id="mm-close" type="button" aria-label="Schließen">',
            '      <i class="fas fa-times"></i>',
            '    </button>',
            '  </div>',
            '  <div id="mm-body">',
            '    <div id="mm-loader"><div id="mm-spinner"></div></div>',
            '    <iframe id="mm-iframe" src="about:blank" frameborder="0"',
            '      allowfullscreen sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-top-navigation-by-user-activation">',
            '    </iframe>',
            '  </div>',
            '</div>'
        ].join('');

        document.body.appendChild(overlay);
        _cache();
        _bindEvents();
    }

    function _cache() {
        overlay  = document.getElementById('mm-overlay');
        card     = document.getElementById('mm-card');
        titleEl  = document.getElementById('mm-title');
        closeBtn = document.getElementById('mm-close');
        body     = document.getElementById('mm-body');
        iframe   = document.getElementById('mm-iframe');
        loader   = document.getElementById('mm-loader');
    }

    /* ------------------------------------------------------------------ */
    /*  Events                                                              */
    /* ------------------------------------------------------------------ */
    function _bindEvents() {
        closeBtn.addEventListener('click', function () { ModalManager.close(); });

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) { ModalManager.close(); }
        });

        document.addEventListener('keydown', function (e) {
            if (_isOpen && (e.key === 'Escape' || e.keyCode === 27)) {
                ModalManager.close();
            }
        });

        iframe.addEventListener('load', function () {
            if (loader) { loader.classList.add('mm-hidden'); }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Public API                                                          */
    /* ------------------------------------------------------------------ */
    var ModalManager = {

        /**
         * Open the modal.
         * @param {string} url     URL to load in the iframe.
         * @param {object} options { title: string }
         */
        open: function (url, options) {
            _build();
            options = options || {};

            titleEl.textContent = options.title || '';

            loader.classList.remove('mm-hidden');
            iframe.src = url;

            document.body.classList.add('mm-locked');
            overlay.classList.add('mm-open');
            _isOpen = true;

            closeBtn.focus();
        },

        /** Close and reset the modal. */
        close: function () {
            if (!overlay) { _cache(); }
            if (!overlay) { return; }

            overlay.classList.remove('mm-open');
            document.body.classList.remove('mm-locked');
            _isOpen = false;

            var delay = _prefersReducedMotion() ? 0 : 300;
            setTimeout(function () {
                if (!_isOpen && iframe) {
                    iframe.src = 'about:blank';
                }
                if (loader) { loader.classList.remove('mm-hidden'); }
            }, delay);
        },

        isOpen: function () { return _isOpen; }
    };

    function _prefersReducedMotion() {
        return global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /* ------------------------------------------------------------------ */
    /*  Fancybox compatibility shim                                         */
    /*  Intercepts: .fancybox class, [data-fancybox], rel="fancybox"       */
    /*  Uses CAPTURE phase + stopImmediatePropagation so jQuery handlers   */
    /*  (which run in bubble phase) never receive the event.               */
    /* ------------------------------------------------------------------ */
    function _bindFancyboxCompat() {
        document.addEventListener('click', function (e) {
            var el = e.target;

            while (el && el !== document) {
                if (el.tagName === 'A' && _isFancyboxTrigger(el)) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    var url   = el.getAttribute('href') || el.getAttribute('data-href') || '';
                    var title = el.getAttribute('title') || el.getAttribute('data-title') || '';
                    if (url && url !== '#') {
                        ModalManager.open(url, { title: title });
                    }
                    return;
                }
                el = el.parentElement;
            }
        }, true); /* true = capture phase — fires before any bubble-phase handler */
    }

    function _isFancyboxTrigger(el) {
        var cls = (el.className || '').toLowerCase();
        if (cls.indexOf('fancybox') !== -1) { return true; }
        if (el.hasAttribute('data-fancybox')) { return true; }
        var rel = (el.getAttribute('rel') || '').toLowerCase();
        if (rel === 'fancybox') { return true; }
        return false;
    }

    /* ------------------------------------------------------------------ */
    /*  jQuery $.fancybox shim (for Dialog.open / gate.js / etc.)          */
    /*  Called immediately AND re-applied after DOMContentLoaded in case   */
    /*  jQuery loads after this script.                                     */
    /* ------------------------------------------------------------------ */
    function _shimJqueryFancybox() {
        var jq = global.jQuery || global.$;
        if (!jq) { return; }

        /* Always replace — even if $.fancybox already exists (the real one) */
        jq.fancybox = function (opts) {
            opts = opts || {};
            var url = opts.href || opts.content || '';
            ModalManager.open(url, { title: opts.title || '' });
        };
        jq.fancybox.close = function () { ModalManager.close(); };
        jq.fancybox.open  = jq.fancybox;
    }

    /* ------------------------------------------------------------------ */
    /*  Override OpenPopup (base.js) to use modal instead of new window    */
    /* ------------------------------------------------------------------ */
    function _shimOpenPopup() {
        global.OpenPopup = function (target_url, win_name, width, height) {
            var url = target_url;
            if (url.indexOf('ajax=') === -1) {
                url += (url.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
            }
            ModalManager.open(url, { title: '' });
            return false;
        };
    }

    /* ------------------------------------------------------------------ */
    /*  parent.$.fancybox.close() shim — called from inside iframes        */
    /* ------------------------------------------------------------------ */
    function _shimParentClose() {
        if (global !== global.parent) { return; }
        if (!global.$ && !global.jQuery) { return; }
        var jq = global.$ || global.jQuery;
        if (!jq.fancybox) {
            jq.fancybox = {};
        }
        if (typeof jq.fancybox.close !== 'function') {
            jq.fancybox.close = function () { ModalManager.close(); };
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Dialog.open shim (base.js uses Dialog.open internally)             */
    /* ------------------------------------------------------------------ */
    function _shimDialog() {
        if (typeof global.Dialog !== 'undefined' && typeof global.Dialog.open === 'function') {
            global.Dialog.open = function (url, width, height) {
                ModalManager.open(url, { title: '' });
                return false;
            };
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Init                                                                */
    /* ------------------------------------------------------------------ */
    function _init() {
        _build();
        _shimJqueryFancybox(); /* re-apply after all scripts have loaded */
        _shimDialog();
    }

    /* Run capture-phase interceptor and jQuery shim immediately so they are
       active before base.js or any inline $(function(){}) handler executes.
       DOM-dependent setup (_build, _shimDialog) still waits for DOMContentLoaded. */
    _bindFancyboxCompat();
    _shimJqueryFancybox();
    _shimOpenPopup();
    _shimParentClose();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _init);
    } else {
        _init();
    }

    global.ModalManager = ModalManager;

}(window));
