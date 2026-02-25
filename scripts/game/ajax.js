/**
 * SmartMoons AJAX Helper v2.0
 *
 * - No window.location.reload() anywhere
 * - Queue/action forms: POST → server returns {ok:true} → refreshPageContent()
 * - Forum: dedicated handlers per action (reply, new topic, edit, delete, report)
 * - Resource bar: polls every 10s + updated from every AJAX response
 * - Progressive enhancement: without JS everything works via normal form POST
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
     * Apply a resource data map {name: {current, max, production}} to the DOM.
     * Called from poll responses and from AJAX responses that include a
     * `resources` key.
     */
    function applyResources(data) {
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
            var valEl = document.getElementById('current_' + name);
            if (valEl && info.current !== undefined) {
                valEl.setAttribute('data-real', info.current);
            }
        });
    }

    /**
     * Fetch current resources from server and apply to DOM.
     * Silent fail — bar updates on next poll.
     */
    function refreshResources() {
        $.getJSON('game.php', { page: 'overview', ajax: 'resources' })
            .done(function (data) { applyResources(data); })
            .fail(function () { /* silent */ });
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
                if (!opts.noRefresh && resp && resp.resources) {
                    applyResources(resp.resources);
                } else if (!opts.noRefresh) {
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
                if (msg && typeof msg === 'string') toastSuccess(msg);
                refreshPageContent(window.location.href);
            })
            .fail(function (errMsg) {
                toastError(errMsg || 'Aktion fehlgeschlagen.');
            });
    }

    /* ------------------------------------------------------------------ */
    /* In-place page content refresh (replaces window.location.reload)    */
    /* ------------------------------------------------------------------ */

    /**
     * Fetch the current page as plain HTML and swap the main content
     * block in-place. Targets .content_page first, then #content.
     * Also re-runs inline scripts inside the new content.
     */
    function refreshPageContent(url) {
        url = url || window.location.href;
        /* Strip AJAX params so server returns full HTML */
        url = url.replace(/[?&]ajax=[^&]*/g, '').replace(/[?&]X-Requested-With=[^&]*/g, '');

        $.ajax({
            url:      url,
            type:     'GET',
            dataType: 'html',
            success: function (html) {
                var $doc        = $('<div>').append($.parseHTML(html, document, false));
                var $newContent = $doc.find('.content_page').first();
                if (!$newContent.length) $newContent = $doc.find('#content').first();

                var $target = $('.content_page').first();
                if (!$target.length) $target = $('#content').first();

                if ($newContent.length && $target.length) {
                    $target.replaceWith($newContent);
                    /* Re-execute inline scripts in the swapped content */
                    $newContent.find('script').each(function () {
                        if (!$(this).attr('src')) {
                            try { eval($(this).text()); } catch (ex) { /* silent */ }
                        }
                    });
                }
                refreshResources();
            },
            error: function () { /* silent */ }
        });
    }

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
     * from the server every `intervalMs` milliseconds (default 10s).
     * The existing resourceTicker reads these attributes each second,
     * so the visual display updates automatically after each poll.
     *
     * Only starts if the resource bar is present in the DOM.
     * Skips poll if vacation mode is active (data-vmode=1 on body).
     */
    function startResourcePolling(intervalMs) {
        intervalMs = intervalMs || 10000;

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
     * Intercept queue action forms on buildings / research / shipyard /
     * officier pages. Selector covers:
     *   .queue-action form   — cancel/remove buttons in the queue panel
     *   .full-width-form     — "Build" / "Level up" buttons on tech cards
     *   form[data-ajax]      — opt-in on any other form
     *
     * On success: swaps page content in-place — NO window.location.reload().
     * On error: shows toast with server error message.
     */
    function initQueueForms() {
        $(document).on('submit.smajax-queue', '.queue-action form, .full-width-form, form[data-ajax]', function (e) {
            /* Forum forms are handled separately by initForumAjax() */
            if ($(this).closest('.forum-container').length) return;

            e.preventDefault();

            var $form    = $(this);
            var url      = $form.attr('action') || window.location.href;
            var payload  = {};
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
                    /* Swap page content in-place — no reload */
                    refreshPageContent(url);
                },
                error: function () {
                    $btn.prop('disabled', false).html(origHtml);
                    toastError('Aktion fehlgeschlagen.');
                }
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Forum AJAX handlers                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Reload only the posts list inside a topic view without a full page
     * reload. Fetches the topic page (GET) and swaps the posts + reply form.
     */
    function _reloadForumThread(topicUrl) {
        $.ajax({
            url:      topicUrl,
            type:     'GET',
            dataType: 'html',
            success: function (html) {
                var $doc = $('<div>').append($.parseHTML(html, document, false));

                /* Swap individual posts */
                var $newPosts = $doc.find('.fl-post');
                var $oldPosts = $('.forum-container .fl-post');
                if ($newPosts.length && $oldPosts.length) {
                    var $lastPost = $oldPosts.last();
                    $oldPosts.not($oldPosts.last()).remove();
                    $lastPost.replaceWith($newPosts);
                } else if ($newPosts.length) {
                    /* No existing posts (empty thread) — insert before reply box */
                    $('.forum-container .fl-box').last().before($newPosts);
                }

                /* Scroll to last post */
                var $last = $('.forum-container .fl-post').last();
                if ($last.length) {
                    $('html, body').animate({ scrollTop: $last.offset().top - 80 }, 400);
                }
            },
            error: function () { /* silent */ }
        });
    }

    function initForumAjax() {

        /* ── Reply (create_post) ── */
        $(document).on('submit.smajax-forum-reply', '.forum-container form', function (e) {
            var $form = $(this);
            if (!$form.find('[name="create_post"]').length) return;
            e.preventDefault();

            var url       = $form.attr('action');
            var $textarea = $form.find('textarea[name="content"]');
            var origText  = $textarea.val();
            var payload   = {};
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
                    $btn.prop('disabled', false).html(origHtml);
                    if (resp && resp.ok === false) {
                        toastError((resp.error) || 'Fehler beim Senden.');
                        return;
                    }
                    $textarea.val('');
                    toastSuccess('Beitrag gesendet.');
                    _reloadForumThread(url);
                },
                error: function (xhr) {
                    $btn.prop('disabled', false).html(origHtml);
                    /* Text preservation on error */
                    $textarea.val(origText);
                    var msg = '';
                    try { msg = JSON.parse(xhr.responseText).error || ''; } catch (ex) { /* empty */ }
                    toastError(msg || 'Fehler beim Senden.');
                }
            });
        });

        /* ── New topic (create_topic) ── */
        $(document).on('submit.smajax-forum-newtopic', '.forum-container form', function (e) {
            var $form = $(this);
            if (!$form.find('[name="create_topic"]').length) return;
            e.preventDefault();

            var url     = $form.attr('action');
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
                    $btn.prop('disabled', false).html(origHtml);
                    if (resp && resp.topicId) {
                        /* Navigate to new topic without reload via pushState + content swap */
                        var newUrl = 'game.php?page=forum&mode=topic&id=' + resp.topicId;
                        history.pushState(null, '', newUrl);
                        refreshPageContent(newUrl);
                    } else if (resp && resp.ok === false) {
                        toastError(resp.error || 'Fehler beim Erstellen.');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html(origHtml);
                    toastError('Fehler beim Erstellen des Themas.');
                }
            });
        });

        /* ── Inline edit (edit_post) ── */
        $(document).on('submit.smajax-forum-edit', '.fl-inline-edit form', function (e) {
            e.preventDefault();
            var $form   = $(this);
            var url     = $form.attr('action');
            var payload = {};
            $form.serializeArray().forEach(function (f) { payload[f.name] = f.value; });
            var postId  = payload.post_id;

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
                    $btn.prop('disabled', false).html(origHtml);
                    if (resp && resp.ok === false) {
                        toastError(resp.error || 'Fehler beim Speichern.');
                        return;
                    }
                    /* Reload the thread so the updated post content is shown */
                    var topicUrl = window.location.href;
                    _reloadForumThread(topicUrl);
                    toastSuccess('Beitrag aktualisiert.');
                },
                error: function () {
                    $btn.prop('disabled', false).html(origHtml);
                    toastError('Fehler beim Speichern.');
                }
            });
        });

        /* ── Delete post ── */
        $(document).on('submit.smajax-forum-delete', '.fl-action-btn-delete', function (e) {
            /* handled via button inside a form with onsubmit confirm */
        });
        $(document).on('submit.smajax-forum-deleteform', '.forum-container form[action*="delete_post"]', function (e) {
            e.preventDefault();
            var $form   = $(this);
            var url     = $form.attr('action');
            var payload = {};
            $form.serializeArray().forEach(function (f) { payload[f.name] = f.value; });
            var postId  = payload.post_id;

            $.ajax({
                url:      url,
                type:     'POST',
                data:     payload,
                dataType: 'json',
                headers:  { 'X-Requested-With': 'XMLHttpRequest' },
                success: function (resp) {
                    if (resp && resp.ok === false) {
                        toastError(resp.error || 'Fehler beim Löschen.');
                        return;
                    }
                    /* Remove post element from DOM */
                    var $post = $('#post-' + postId);
                    if ($post.length) {
                        $post.fadeOut(300, function () { $post.remove(); });
                    }
                    toastSuccess('Beitrag gelöscht.');
                },
                error: function () {
                    toastError('Fehler beim Löschen.');
                }
            });
        });

        /* ── Report post ── */
        $(document).on('submit.smajax-forum-report', '.fl-report-form form', function (e) {
            e.preventDefault();
            var $form   = $(this);
            var url     = $form.attr('action');
            var payload = {};
            $form.serializeArray().forEach(function (f) { payload[f.name] = f.value; });
            var postId  = payload.post_id;

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
                    $btn.prop('disabled', false).html(origHtml);
                    var $reportDiv = $('#report-form-' + postId);
                    if ($reportDiv.length) $reportDiv.hide();
                    toastSuccess('Beitrag gemeldet.');
                },
                error: function () {
                    $btn.prop('disabled', false).html(origHtml);
                    toastError('Fehler beim Melden.');
                }
            });
        });
    }

    /* Auto-start polling, queue interceptor and forum AJAX when DOM is ready */
    $(function () {
        startResourcePolling(10000);
        initQueueForms();
        initForumAjax();
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
        refreshPageContent:    refreshPageContent,
        applyResources:        applyResources,
        startResourcePolling:  startResourcePolling,
        toast:                 toast,
        toastError:            toastError,
        toastSuccess:          toastSuccess
    };

}(jQuery));
