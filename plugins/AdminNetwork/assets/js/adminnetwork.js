/**
 * 2MoonsCE — AdminNetwork Plugin
 * Frontend chat logic for the admin panel.
 *
 * Requires: window.AdminNetwork = { hubUrl, instanceKey, instanceName, pollInterval }
 * AN_AJAX_URL must be set on the chat page.
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 */
(function () {
    'use strict';

    // ── Only run on the chat page ─────────────────────────────────────────────
    var chatBox = document.getElementById('an-messages');
    if (!chatBox) return;

    var input      = document.getElementById('an-input');
    var sendBtn    = document.getElementById('an-send-btn');
    var statusDot  = document.getElementById('an-status-dot');
    var statusText = document.getElementById('an-status-text');
    var msgCount   = document.getElementById('an-msg-count');
    var onlineList = document.getElementById('an-online-list');
    var refreshBtn = document.getElementById('an-refresh-online');

    var cfg        = window.AdminNetwork || {};
    var ajaxUrl    = window.AN_AJAX_URL  || 'admin.php?page=plugin_admin_network';

    var lastId     = 0;
    var pollTimer  = null;
    var totalMsgs  = 0;
    var myInstance = cfg.instanceName || '';

    // ── Utility ───────────────────────────────────────────────────────────────
    function setStatus(online) {
        if (!statusDot || !statusText) return;
        statusDot.className  = 'an-status-dot ' + (online ? 'online' : 'offline');
        statusText.textContent = online ? 'Verbunden' : 'Nicht verbunden';
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function formatTime(unixTs) {
        var d = new Date(unixTs * 1000);
        var pad = function(n){ return n < 10 ? '0'+n : n; };
        return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    function scrollToBottom() {
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // ── Render messages ───────────────────────────────────────────────────────
    function renderMessages(messages) {
        if (!messages || !messages.length) return;

        var placeholder = chatBox.querySelector('.an-messages-placeholder');
        if (placeholder) placeholder.remove();

        var wasAtBottom = (chatBox.scrollTop + chatBox.clientHeight) >= (chatBox.scrollHeight - 40);

        messages.forEach(function (msg) {
            var isMine = (msg.instance_name === myInstance);
            var div = document.createElement('div');
            div.className = 'an-msg' + (isMine ? ' an-msg-mine' : ' an-msg-other');
            div.setAttribute('data-id', msg.id);

            var deleteBtn = isMine
                ? '<button class="an-delete-btn" data-id="' + msg.id + '" title="Löschen"><i class="fas fa-trash-alt"></i></button>'
                : '';

            div.innerHTML =
                '<div class="an-msg-meta">' +
                    '<span class="an-msg-instance">' + escHtml(msg.instance_name) + '</span>' +
                    '<span class="an-msg-time">' + formatTime(msg.created_at) + '</span>' +
                    deleteBtn +
                '</div>' +
                '<div class="an-msg-text">' + escHtml(msg.text) + '</div>';

            chatBox.appendChild(div);
            lastId = Math.max(lastId, parseInt(msg.id, 10));
            totalMsgs++;
        });

        if (msgCount) msgCount.textContent = totalMsgs;
        if (wasAtBottom) scrollToBottom();
    }

    // ── Poll loop ─────────────────────────────────────────────────────────────
    function poll() {
        fetch(ajaxUrl + '&an_action=poll_ajax&since_id=' + lastId, { method: 'GET' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                setStatus(true);
                if (data.ok && data.messages && data.messages.length) {
                    renderMessages(data.messages);
                }
            })
            .catch(function () {
                setStatus(false);
            });
    }

    function startPolling() {
        poll();
        var interval = Math.max(5000, cfg.pollInterval || 10000);
        pollTimer = window.setInterval(poll, interval);
    }

    // ── Send message ──────────────────────────────────────────────────────────
    function sendMessage() {
        var text = (input ? input.value.trim() : '');
        if (!text) return;

        if (sendBtn) sendBtn.disabled = true;
        if (input)   input.value = '';

        var fd = new FormData();
        fd.append('an_action', 'send_ajax');
        fd.append('text', text);

        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    poll(); // immediate refresh
                } else {
                    showError(data.error || 'Senden fehlgeschlagen.');
                    if (input) input.value = text; // restore
                }
            })
            .catch(function () {
                showError('Verbindungsfehler beim Senden.');
                if (input) input.value = text;
            })
            .finally(function () {
                if (sendBtn) sendBtn.disabled = false;
                if (input)   input.focus();
            });
    }

    // ── Delete message ─────────────────────────────────────────────────────────
    function deleteMessage(msgId) {
        var fd = new FormData();
        fd.append('an_action', 'delete_ajax');
        fd.append('message_id', msgId);

        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    var el = chatBox.querySelector('[data-id="' + msgId + '"]');
                    if (el) {
                        el.classList.add('an-msg-deleting');
                        setTimeout(function () { el.remove(); totalMsgs--; if(msgCount) msgCount.textContent = totalMsgs; }, 300);
                    }
                }
            });
    }

    // ── Online instances ───────────────────────────────────────────────────────
    function refreshOnline() {
        if (!onlineList) return;
        fetch(ajaxUrl + '&an_action=online_ajax', { method: 'GET' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.instances) { onlineList.innerHTML = '<li class="an-online-empty">Keine Daten</li>'; return; }
                if (!data.instances.length) { onlineList.innerHTML = '<li class="an-online-empty">Keine anderen Instanzen online</li>'; return; }
                var html = '';
                data.instances.forEach(function (inst) {
                    var isMine = inst.name === myInstance;
                    html += '<li class="an-online-item' + (isMine ? ' an-online-mine' : '') + '">' +
                        '<i class="fas fa-circle an-dot-green"></i> ' +
                        '<span>' + escHtml(inst.name) + '</span>' +
                        (isMine ? ' <em>(ich)</em>' : '') +
                        '</li>';
                });
                onlineList.innerHTML = html;
            })
            .catch(function () {
                if (onlineList) onlineList.innerHTML = '<li class="an-online-empty">Fehler beim Laden</li>';
            });
    }

    // ── Error display ──────────────────────────────────────────────────────────
    function showError(msg) {
        var div = document.createElement('div');
        div.className = 'an-toast-error';
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(function () { div.remove(); }, 4000);
    }

    // ── Event listeners ───────────────────────────────────────────────────────
    if (sendBtn) sendBtn.addEventListener('click', sendMessage);
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });
    }

    if (chatBox) {
        chatBox.addEventListener('click', function (e) {
            var btn = e.target.closest('.an-delete-btn');
            if (btn && confirm('Nachricht löschen?')) {
                deleteMessage(parseInt(btn.getAttribute('data-id'), 10));
            }
        });
    }

    if (refreshBtn) refreshBtn.addEventListener('click', refreshOnline);

    // ── Init ───────────────────────────────────────────────────────────────────
    setStatus(false);
    startPolling();
    refreshOnline();
    window.setInterval(refreshOnline, 30000);

})();
