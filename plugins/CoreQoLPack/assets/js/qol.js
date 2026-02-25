/**
 * CoreQoLPack – qol.js
 *
 * Features:
 *  1) AJAX Form Intercept  – forms with class="ajax-form"
 *  2) Forum Autosave       – localStorage, 5s interval, per thread-id
 *  3) Live Resource Sync   – fetch every 10s, DOM-update only
 *  4) Toast Notifications  – minimal, no external framework
 */
(function () {
    'use strict';

    // ── Configuration ─────────────────────────────────────────────────────────

    var CFG = (function () {
        if (window.qolConfig && typeof window.qolConfig === 'object') {
            return window.qolConfig;
        }
        return {
            apiUrl:       'game.php',
            syncInterval: 10000,
            autosaveMs:   5000,
        };
    })();

    var SYNC_INTERVAL = Math.max(5000, parseInt(CFG.syncInterval, 10) || 10000);
    var AUTOSAVE_MS   = Math.max(2000, parseInt(CFG.autosaveMs,   10) || 5000);
    var API_URL       = (typeof CFG.apiUrl === 'string' && CFG.apiUrl) ? CFG.apiUrl : 'game.php';

    // ═══════════════════════════════════════════════════════════════════════════
    // 1) TOAST NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════════════════

    var QoLToast = (function () {
        var ICONS = {
            success: 'fa-check-circle',
            error:   'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info:    'fa-info-circle',
        };

        function getContainer() {
            return document.getElementById('qol-toast-container');
        }

        function dismiss(el) {
            el.classList.add('qol-toast--out');
            el.addEventListener('animationend', function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, { once: true });
        }

        /**
         * Show a toast notification.
         * @param {string} message
         * @param {'success'|'error'|'warning'|'info'} [type='info']
         * @param {number} [duration=4000]  ms before auto-dismiss (0 = sticky)
         */
        function show(message, type, duration) {
            var container = getContainer();
            if (!container) {
                return;
            }

            type     = type || 'info';
            duration = (duration === undefined) ? 4000 : duration;

            var toast = document.createElement('div');
            toast.className = 'qol-toast qol-toast--' + type;
            toast.setAttribute('role', 'status');

            var iconClass = ICONS[type] || 'fa-info-circle';

            toast.innerHTML =
                '<div class="qol-toast__icon"><i class="fas ' + iconClass + '"></i></div>' +
                '<div class="qol-toast__body">' + escapeHtml(message) + '</div>' +
                '<button class="qol-toast__close" aria-label="Schließen"><i class="fas fa-times"></i></button>';

            toast.querySelector('.qol-toast__close').addEventListener('click', function () {
                dismiss(toast);
            });

            container.appendChild(toast);

            if (duration > 0) {
                setTimeout(function () {
                    if (toast.parentNode) {
                        dismiss(toast);
                    }
                }, duration);
            }
        }

        return { show: show };
    })();

    // Expose globally so PHP-injected inline scripts can also fire toasts.
    window.QoLToast = QoLToast;

    // ═══════════════════════════════════════════════════════════════════════════
    // Utility: HTML escape
    // ═══════════════════════════════════════════════════════════════════════════

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 2) AJAX FORM INTERCEPT
    // ═══════════════════════════════════════════════════════════════════════════

    var QoLAjaxForms = (function () {

        function handleResponse(form, data) {
            // Expected: { ok: true, message?: string, redirect?: string,
            //             resources?: { name: { current, max, production } } }
            if (data.resources && typeof data.resources === 'object') {
                QoLResourceSync.applyResources(data.resources);
            }

            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            if (data.message) {
                QoLToast.show(data.message, data.ok ? 'success' : 'error');
            } else if (data.ok) {
                QoLToast.show('Erfolgreich gespeichert.', 'success');
            }

            // Re-enable form
            form.classList.remove('ajax-form--loading');
            var submit = form.querySelector('[type="submit"]');
            if (submit) {
                submit.disabled = false;
            }
        }

        function interceptForm(form) {
            if (form._qolBound) {
                return;
            }
            form._qolBound = true;

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                form.classList.add('ajax-form--loading');
                var submit = form.querySelector('[type="submit"]');
                if (submit) {
                    submit.disabled = true;
                }

                var action = form.getAttribute('action') || window.location.href;
                var method = (form.getAttribute('method') || 'POST').toUpperCase();
                var body   = new FormData(form);

                fetch(action, {
                    method:  method,
                    body:    method === 'GET' ? undefined : body,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    handleResponse(form, data);
                })
                .catch(function (err) {
                    // Fallback: normal submit
                    form.classList.remove('ajax-form--loading');
                    if (submit) {
                        submit.disabled = false;
                    }
                    QoLToast.show('Fehler beim Senden. Normaler Submit wird ausgeführt.', 'warning');
                    form._qolBound = false;
                    form.submit();
                });
            });
        }

        function init() {
            // Bind existing forms
            document.querySelectorAll('form.ajax-form').forEach(interceptForm);

            // Watch for dynamically added forms
            if (typeof MutationObserver !== 'undefined') {
                new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        mutation.addedNodes.forEach(function (node) {
                            if (node.nodeType !== 1) {
                                return;
                            }
                            if (node.matches && node.matches('form.ajax-form')) {
                                interceptForm(node);
                            }
                            node.querySelectorAll && node.querySelectorAll('form.ajax-form').forEach(interceptForm);
                        });
                    });
                }).observe(document.body, { childList: true, subtree: true });
            }
        }

        return { init: init };
    })();

    // ═══════════════════════════════════════════════════════════════════════════
    // 3) FORUM AUTOSAVE
    // ═══════════════════════════════════════════════════════════════════════════

    var QoLAutosave = (function () {
        var PREFIX = 'qol_autosave_';

        function storageKey(threadId) {
            return PREFIX + (threadId || 'global');
        }

        function getThreadId() {
            // Try URL param ?id=...
            var m = window.location.search.match(/[?&]id=(\d+)/);
            if (m) {
                return m[1];
            }
            // Try data attribute on textarea
            var ta = document.querySelector('textarea[data-thread-id]');
            if (ta) {
                return ta.getAttribute('data-thread-id');
            }
            return 'global';
        }

        function findTextareas() {
            return Array.prototype.slice.call(
                document.querySelectorAll(
                    '.forum-container textarea, ' +
                    'textarea[name="text"], ' +
                    'textarea[name="content"], ' +
                    'textarea.forum-reply-input'
                )
            );
        }

        function saveAll(threadId) {
            var tas = findTextareas();
            if (tas.length === 0) {
                return;
            }
            var data = tas.map(function (ta) {
                return {
                    name:  ta.name || ta.id || '',
                    value: ta.value,
                };
            });
            try {
                localStorage.setItem(storageKey(threadId), JSON.stringify({
                    ts:   Date.now(),
                    data: data,
                }));
            } catch (e) {}
        }

        function restore(threadId) {
            try {
                var raw = localStorage.getItem(storageKey(threadId));
                if (!raw) {
                    return;
                }
                var saved = JSON.parse(raw);
                if (!saved || !Array.isArray(saved.data)) {
                    return;
                }
                // Only restore if saved within last 24 hours
                if (Date.now() - saved.ts > 86400000) {
                    localStorage.removeItem(storageKey(threadId));
                    return;
                }

                var tas = findTextareas();
                saved.data.forEach(function (item) {
                    tas.forEach(function (ta) {
                        if ((ta.name || ta.id || '') === item.name && item.value && !ta.value) {
                            ta.value = item.value;
                            updateIndicator(ta, 'restored');
                        }
                    });
                });
            } catch (e) {}
        }

        function clearSaved(threadId) {
            try {
                localStorage.removeItem(storageKey(threadId));
            } catch (e) {}
        }

        function updateIndicator(ta, state) {
            var wrap = ta.closest
                ? ta.closest('.forum-reply-wrap, .fl-post-form, .forum-post-wrap')
                : null;
            if (!wrap) {
                return;
            }
            var ind = wrap.querySelector('.qol-autosave-indicator');
            if (!ind) {
                ind = document.createElement('div');
                ind.className = 'qol-autosave-indicator';
                ta.parentNode.appendChild(ind);
            }
            ind.className = 'qol-autosave-indicator';
            if (state === 'saved') {
                ind.className += ' qol-autosave-indicator--saved';
                ind.innerHTML = '<i class="fas fa-check"></i> Autosave';
            } else if (state === 'restored') {
                ind.className += ' qol-autosave-indicator--saved';
                ind.innerHTML = '<i class="fas fa-history"></i> Wiederhergestellt';
            } else {
                ind.className += ' qol-autosave-indicator--unsaved';
                ind.innerHTML = '<i class="fas fa-circle"></i> Ungespeichert';
            }
        }

        function init() {
            var tas = findTextareas();
            if (tas.length === 0) {
                return;
            }

            var threadId = getThreadId();
            restore(threadId);

            // Mark as unsaved on input
            tas.forEach(function (ta) {
                ta.addEventListener('input', function () {
                    updateIndicator(ta, 'unsaved');
                });
            });

            // Periodic autosave
            var saveTimer = setInterval(function () {
                if (findTextareas().length === 0) {
                    clearInterval(saveTimer);
                    return;
                }
                saveAll(threadId);
                findTextareas().forEach(function (ta) {
                    if (ta.value) {
                        updateIndicator(ta, 'saved');
                    }
                });
            }, AUTOSAVE_MS);

            // Clear on form submit (post sent – no need to restore)
            document.querySelectorAll('.forum-container form, form[data-forum]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    clearSaved(threadId);
                });
            });
        }

        return { init: init };
    })();

    // ═══════════════════════════════════════════════════════════════════════════
    // 4) LIVE RESOURCE SYNC
    // ═══════════════════════════════════════════════════════════════════════════

    var QoLResourceSync = (function () {
        var syncTimer = null;

        /**
         * Apply a resources map from the server to the DOM in-place.
         * Compatible with the existing resource DOM structure used by base.js.
         *
         * @param {Object} resources  { name: { current, max, production } }
         */
        function applyResources(resources) {
            if (!resources || typeof resources !== 'object') {
                return;
            }
            Object.keys(resources).forEach(function (name) {
                var data = resources[name];
                if (!data) {
                    return;
                }

                // Update text value (same element resourceTicker writes to)
                var valEl = document.getElementById('current_' + name);
                if (valEl && data.current !== undefined) {
                    var current = parseInt(data.current, 10) || 0;
                    valEl.textContent = current.toLocaleString('de-DE');
                    valEl.classList.add('qol-res-pulse');
                    setTimeout(function () {
                        valEl.classList.remove('qol-res-pulse');
                    }, 400);
                }

                // Update data attributes on .res-item so resbar animations pick up new values
                var resItem = document.querySelector('.res-item[data-res-name="' + name + '"]');
                if (resItem) {
                    if (data.current   !== undefined) resItem.setAttribute('data-res-current',    String(data.current));
                    if (data.max       !== undefined) resItem.setAttribute('data-res-max',         String(data.max));
                    if (data.production !== undefined) resItem.setAttribute('data-res-production', String(data.production));
                }
            });
        }

        function fetchAndApply() {
            fetch(API_URL + '?page=resourceSync&ajax=1', {
                method:  'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache:   'no-store',
            })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (data) {
                if (data && data.resources) {
                    applyResources(data.resources);
                }
            })
            .catch(function () {
                // Silent fail – resource ticker continues working independently
            });
        }

        function start(intervalMs) {
            if (syncTimer !== null) {
                return;
            }
            intervalMs = Math.max(5000, intervalMs || SYNC_INTERVAL);
            syncTimer  = setInterval(fetchAndApply, intervalMs);
        }

        function stop() {
            if (syncTimer !== null) {
                clearInterval(syncTimer);
                syncTimer = null;
            }
        }

        return { start: start, stop: stop, applyResources: applyResources };
    })();

    // Expose for other scripts (e.g. AJAX form handler)
    window.QoLResourceSync = QoLResourceSync;

    // ═══════════════════════════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════════════════════════

    function init() {
        try { QoLAjaxForms.init();  } catch (e) { console.error('[CoreQoLPack] AjaxForms init error:', e); }
        try { QoLAutosave.init();   } catch (e) { console.error('[CoreQoLPack] Autosave init error:', e); }
        try { QoLResourceSync.start(SYNC_INTERVAL); } catch (e) { console.error('[CoreQoLPack] ResourceSync init error:', e); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API
    window.CoreQoLPack = {
        toast:  QoLToast,
        sync:   QoLResourceSync,
    };

})();
