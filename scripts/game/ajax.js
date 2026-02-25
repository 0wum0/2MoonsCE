/**
 * SmartMoons AJAX Helper v1.0
 *
 * Central AJAX infrastructure for in-game actions.
 * - POST helper with X-Requested-With header (server detects AJAX)
 * - Token handling (reads hidden input[name=token] from nearest form)
 * - Toast feedback via SmartNotif (falls back gracefully)
 * - Resource bar refresh after actions
 * - Progressive enhancement: falls back to normal form submit if JS unavailable
 *
 * Usage:
 *   SmAjax.post('game.php?page=buildings', {cmd:'build', id:1, level:2})
 *     .then(function(data){ ... })
 *     .catch(function(err){ ... });
 *
 *   SmAjax.action(formElement);  // intercepts a form submit
 */
var SmAjax = (function ($) {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* Internal helpers                                                     */
    /* ------------------------------------------------------------------ */

    function toast(msg, icon, color) {
        if (typeof SmartNotif !== 'undefined' && SmartNotif.toast) {
            SmartNotif.toast(msg, icon || 'check-circle', color || '#00f3ff');
        }
    }

    function toastError(msg) {
        toast(msg, 'exclamation-circle', '#ef4444');
    }

    function toastSuccess(msg) {
        toast(msg, 'check-circle', '#22c55e');
    }

    /**
     * Read the CSRF/session token from a form or from the page.
     * Looks for input[name=token] in the form first, then page-wide.
     */
    function getToken(form) {
        var el;
        if (form) {
            el = form.querySelector('input[name="token"]');
        }
        if (!el) {
            el = document.querySelector('input[name="token"]');
        }
        return el ? el.value : '';
    }

    /**
     * Refresh resource bar data-attributes and re-trigger the ticker.
     * Called after any action that may change planet resources.
     * Fetches `game.php?page=overview&ajax=resources` which returns JSON.
     */
    function refreshResources() {
        $.getJSON('game.php', { page: 'overview', ajax: 'resources' })
            .done(function (data) {
                if (!data || typeof data !== 'object') return;
                $.each(data, function (name, info) {
                    var item = document.querySelector('.res-item[data-res-name="' + name + '"]');
                    if (!item) return;
                    if (info.current !== undefined) {
                        item.setAttribute('data-res-current', info.current);
                    }
                    if (info.max !== undefined) {
                        item.setAttribute('data-res-max', info.max);
                    }
                    if (info.production !== undefined) {
                        item.setAttribute('data-res-production', info.production);
                    }
                    /* Update the live display value if resourceTicker isn't running */
                    var valEl = document.getElementById('current_' + name);
                    if (valEl && info.current !== undefined) {
                        valEl.setAttribute('data-real', info.current);
                    }
                });
            })
            .fail(function () {
                /* Silent fail — resource bar will update on next page load */
            });
    }

    /* ------------------------------------------------------------------ */
    /* Core POST                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Perform an AJAX POST to url with data.
     * Always adds X-Requested-With: XMLHttpRequest so PHP can detect it.
     * Returns a jQuery Deferred promise resolving to parsed JSON or raw text.
     *
     * @param {string} url
     * @param {Object} data
     * @param {Object} [options]  { token: '...', noRefresh: bool }
     * @returns {$.Deferred}
     */
    function post(url, data, options) {
        var opts   = options || {};
        var deferred = $.Deferred();

        var payload = $.extend({}, data);
        if (!payload.token) {
            payload.token = opts.token || getToken(opts.form || null);
        }

        $.ajax({
            url:         url,
            type:        'POST',
            data:        payload,
            dataType:    'json',
            headers:     { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (resp) {
                if (!opts.noRefresh) {
                    refreshResources();
                }
                deferred.resolve(resp);
            },
            error: function (xhr) {
                var msg = '';
                try {
                    var json = JSON.parse(xhr.responseText);
                    msg = json.error || json.message || '';
                } catch (e) { /* empty */ }
                deferred.reject(msg || 'Fehler beim Senden der Anfrage.');
            }
        });

        return deferred.promise();
    }

    /* ------------------------------------------------------------------ */
    /* GET helper                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Perform an AJAX GET.
     */
    function get(url, data, options) {
        var opts     = options || {};
        var deferred = $.Deferred();

        $.ajax({
            url:      url,
            type:     'GET',
            data:     $.extend({}, data),
            dataType: 'json',
            headers:  { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (resp) {
                deferred.resolve(resp);
            },
            error: function (xhr) {
                var msg = '';
                try { msg = JSON.parse(xhr.responseText).error || ''; } catch (e) { /* empty */ }
                deferred.reject(msg || 'Fehler bei der Anfrage.');
            }
        });

        return deferred.promise();
    }

    /* ------------------------------------------------------------------ */
    /* Form action interceptor                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Intercept a form's submit event for AJAX handling.
     * Reads action URL + all form fields. Shows spinner on submit button.
     * On success: calls successCallback(data) or shows data.message as toast.
     * On error: shows error toast.
     * Falls back to normal submit if AJAX not available.
     *
     * @param {HTMLFormElement|jQuery} formEl
     * @param {Function} [successCallback]
     * @param {Object}   [options]  { noRefresh: bool, noToast: bool }
     */
    function action(formEl, successCallback, options) {
        var $form  = $(formEl);
        var opts   = options || {};

        $form.on('submit.smajax', function (e) {
            e.preventDefault();

            var url     = $form.attr('action') || window.location.href;
            var payload = {};
            $form.serializeArray().forEach(function (f) {
                payload[f.name] = f.value;
            });

            var $btn = $form.find('[type="submit"]');
            var origText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            post(url, payload, { form: formEl[0] || formEl, noRefresh: opts.noRefresh })
                .done(function (data) {
                    $btn.prop('disabled', false).html(origText);

                    if (!opts.noToast) {
                        var msg = (data && (data.message || data.success)) || '';
                        if (msg) toastSuccess(msg);
                    }

                    if (typeof successCallback === 'function') {
                        successCallback(data);
                    }
                })
                .fail(function (errMsg) {
                    $btn.prop('disabled', false).html(origText);
                    if (!opts.noToast) {
                        toastError(errMsg || 'Ein Fehler ist aufgetreten.');
                    }
                });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Build / queue action shorthand                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Send a build-queue action (build, cancel, accelerate…).
     * Common pattern: POST to current page URL with cmd + id.
     * Shows toast feedback and refreshes resource bar.
     *
     * @param {string} cmd     e.g. 'build', 'cancel', 'research'
     * @param {number} id      element ID
     * @param {Object} [extra] additional POST params
     */
    function queueAction(cmd, id, extra) {
        var payload = $.extend({ cmd: cmd, id: id, ajax: 1 }, extra || {});
        return post(window.location.href, payload)
            .done(function (data) {
                var msg = (data && (data.message || data.success)) || '';
                if (msg) toastSuccess(msg);
                if (data && data.reload) {
                    setTimeout(function () { window.location.reload(); }, 800);
                }
            })
            .fail(function (errMsg) {
                toastError(errMsg || 'Aktion fehlgeschlagen.');
            });
    }

    /* ------------------------------------------------------------------ */
    /* Partial page update                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Load a page section via AJAX and replace a DOM target.
     * Server must return HTML partial when ajax=1 is present.
     *
     * @param {string} url
     * @param {string|Element} target  CSS selector or DOM element to replace content of
     * @param {Object} [data]
     */
    function loadPartial(url, target, data) {
        var $target  = $(target);
        var deferred = $.Deferred();

        $target.addClass('sm-ajax-loading');

        $.ajax({
            url:     url,
            type:    'GET',
            data:    $.extend({ ajax: 1 }, data || {}),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (html) {
                $target.removeClass('sm-ajax-loading').html(html);
                deferred.resolve(html);
            },
            error: function () {
                $target.removeClass('sm-ajax-loading');
                deferred.reject('Seite konnte nicht geladen werden.');
            }
        });

        return deferred.promise();
    }

    /* ------------------------------------------------------------------ */
    /* Periodic resource bar polling                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Start a background poll that refreshes resource bar data-attributes
     * from the server every `intervalMs` milliseconds (default 60s).
     * The existing resourceTicker reads these attributes each second,
     * so the visual display updates automatically after each poll.
     *
     * Only starts if the resource bar is present in the DOM.
     * Skips poll if vacation mode is active (data-vmode=1 on body).
     */
    function startResourcePolling(intervalMs) {
        intervalMs = intervalMs || 60000;

        if (!document.querySelector('.res-item[data-res-name]')) {
            return;
        }

        window.setInterval(function () {
            if (document.body && document.body.getAttribute('data-vmode') === '1') {
                return;
            }
            refreshResources();
        }, intervalMs);
    }

    /* ------------------------------------------------------------------ */
    /* Build-queue form AJAX interceptor                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Intercept queue action forms on buildings / research / shipyard pages.
     * Selector covers:
     *   .queue-action form   — cancel/remove buttons in the queue panel
     *   .full-width-form     — "Build" / "Level up" buttons on tech cards
     *   form[data-ajax]      — opt-in on any other form
     *
     * On success ({ok:true}) the page is reloaded so the queue re-renders
     * server-side. On error the toast shows the server message.
     */
    function initQueueForms() {
        $(document).on('submit.smajax-queue', '.queue-action form, .full-width-form, form[data-ajax]', function (e) {
            e.preventDefault();

            var $form   = $(this);
            var url     = $form.attr('action') || window.location.href;
            var payload = {};
            $form.serializeArray().forEach(function (f) { payload[f.name] = f.value; });

            var $btn     = $form.find('[type="submit"]');
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url:      url,
                type:     'POST',
                data:     payload,
                dataType: 'json',
                headers:  { 'X-Requested-With': 'XMLHttpRequest' },
                success: function (resp) {
                    if (resp && resp.ok === false && resp.error) {
                        $btn.prop('disabled', false).html(origHtml);
                        toastError(resp.error);
                        return;
                    }
                    /* Refresh resources then reload the page */
                    refreshResources();
                    window.location.reload();
                },
                error: function () {
                    $btn.prop('disabled', false).html(origHtml);
                    toastError('Aktion fehlgeschlagen.');
                }
            });
        });
    }

    /* Auto-start polling and queue interceptor when DOM is ready */
    $(function () {
        startResourcePolling(60000);
        initQueueForms();
    });

    /* ------------------------------------------------------------------ */
    /* Public API                                                           */
    /* ------------------------------------------------------------------ */

    return {
        post:                  post,
        get:                   get,
        action:                action,
        queueAction:           queueAction,
        loadPartial:           loadPartial,
        refreshResources:      refreshResources,
        startResourcePolling:  startResourcePolling,
        toast:                 toast,
        toastError:            toastError,
        toastSuccess:          toastSuccess
    };

}(jQuery));
