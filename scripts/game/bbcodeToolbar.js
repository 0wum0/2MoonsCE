/**
 * SmartMoons BBCode Toolbar
 * Zero-dependency, PHP 8.3-compatible BBCode editor toolbar.
 * Wires to any <textarea> via data-bbcode-toolbar="<textareaId>".
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Core helpers
    ------------------------------------------------------------------ */

    /**
     * Insert text around the current selection in a textarea, or at cursor
     * if nothing is selected.
     */
    function insertWrap(textarea, open, close) {
        if (!textarea) { return; }
        textarea.focus();
        var start = textarea.selectionStart;
        var end   = textarea.selectionEnd;
        var sel   = textarea.value.substring(start, end);
        var replacement = open + (sel || '') + close;
        textarea.value =
            textarea.value.substring(0, start) +
            replacement +
            textarea.value.substring(end);
        var cursor = start + open.length + (sel ? sel.length + close.length : 0);
        textarea.selectionStart = sel ? start + open.length : cursor;
        textarea.selectionEnd   = sel ? start + open.length + sel.length : cursor;
        triggerInput(textarea);
    }

    /**
     * Insert a block of text at the cursor position (no wrapping).
     */
    function insertAt(textarea, text) {
        if (!textarea) { return; }
        textarea.focus();
        var start = textarea.selectionStart;
        textarea.value =
            textarea.value.substring(0, start) +
            text +
            textarea.value.substring(textarea.selectionEnd);
        var pos = start + text.length;
        textarea.selectionStart = pos;
        textarea.selectionEnd   = pos;
        triggerInput(textarea);
    }

    /** Fire input event so char-counters etc. stay in sync. */
    function triggerInput(textarea) {
        var ev = document.createEvent('Event');
        ev.initEvent('input', true, true);
        textarea.dispatchEvent(ev);
    }

    /* ------------------------------------------------------------------
       Prompt helpers for tags that need a value
    ------------------------------------------------------------------ */

    function promptColor(textarea) {
        var color = window.prompt('Farbe (z.B. red, #ff0000, rgb(255,0,0)):', '#38bdf8');
        if (color === null || color.trim() === '') { return; }
        insertWrap(textarea, '[color=' + color.trim() + ']', '[/color]');
    }

    function promptSize(textarea) {
        var size = window.prompt('Schriftgröße in px (8–72):', '16');
        if (size === null || size.trim() === '') { return; }
        var n = parseInt(size, 10);
        if (isNaN(n) || n < 8 || n > 72) { return; }
        insertWrap(textarea, '[size=' + n + ']', '[/size]');
    }

    function promptUrl(textarea) {
        var url = window.prompt('URL:', 'https://');
        if (url === null || url.trim() === '') { return; }
        insertWrap(textarea, '[url=' + url.trim() + ']', '[/url]');
    }

    function promptImg(textarea) {
        var url = window.prompt('Bild-URL:', 'https://');
        if (url === null || url.trim() === '') { return; }
        insertWrap(textarea, '[img]', '[/img]');
        /* replace the empty wrap with the actual url */
        var ta = textarea;
        var idx = ta.value.lastIndexOf('[img][/img]');
        if (idx !== -1) {
            ta.value = ta.value.substring(0, idx) +
                '[img]' + url.trim() + '[/img]' +
                ta.value.substring(idx + '[img][/img]'.length);
        }
        triggerInput(ta);
    }

    function insertQuote(textarea) {
        var author = window.prompt('Zitat von (leer lassen für anonym):', '');
        if (author === null) { return; }
        if (author.trim() !== '') {
            insertWrap(textarea, '[quote=' + author.trim() + ']', '[/quote]');
        } else {
            insertWrap(textarea, '[quote]', '[/quote]');
        }
    }

    function insertList(textarea) {
        var type = window.prompt('Listentyp: leer = Punkte, 1 = Nummeriert, a = Alphabetisch', '');
        if (type === null) { return; }
        var open = type.trim() !== '' ? '[list=' + type.trim() + ']' : '[list]';
        insertAt(textarea, open + '\n[*]Element 1\n[*]Element 2\n[/list]');
    }

    function insertTable(textarea) {
        insertAt(textarea,
            '[table]\n' +
            '[tr][th]Kopf 1[/th][th]Kopf 2[/th][/tr]\n' +
            '[tr][td]Zelle 1[/td][td]Zelle 2[/td][/tr]\n' +
            '[/table]'
        );
    }

    /* ------------------------------------------------------------------
       Button definitions
       Each entry: { label, title, action }
       action: function(textarea) or string shorthand 'wrap:open:close' / 'insert:text'
    ------------------------------------------------------------------ */

    var BUTTONS = [
        { label: '<b>B</b>',  title: 'Fett [b]',          action: function(ta){ insertWrap(ta,'[b]','[/b]'); } },
        { label: '<i>I</i>',  title: 'Kursiv [i]',         action: function(ta){ insertWrap(ta,'[i]','[/i]'); } },
        { label: '<u>U</u>',  title: 'Unterstrichen [u]',  action: function(ta){ insertWrap(ta,'[u]','[/u]'); } },
        { label: '<s>S</s>',  title: 'Durchgestrichen [s]',action: function(ta){ insertWrap(ta,'[s]','[/s]'); } },
        { label: '|', title: '', action: null }, /* separator */
        { label: '<i class="fas fa-palette"></i>', title: 'Farbe [color]',   action: promptColor },
        { label: '<i class="fas fa-text-height"></i>', title: 'Schriftgröße [size]', action: promptSize },
        { label: '|', title: '', action: null },
        { label: '<i class="fas fa-align-center"></i>', title: 'Zentriert [center]', action: function(ta){ insertWrap(ta,'[center]','[/center]'); } },
        { label: '<i class="fas fa-align-right"></i>',  title: 'Rechtsbündig [right]', action: function(ta){ insertWrap(ta,'[right]','[/right]'); } },
        { label: '|', title: '', action: null },
        { label: '<i class="fas fa-link"></i>',         title: 'Link [url]',  action: promptUrl },
        { label: '<i class="fas fa-image"></i>',        title: 'Bild [img]',  action: promptImg },
        { label: '|', title: '', action: null },
        { label: '<i class="fas fa-quote-left"></i>',   title: 'Zitat [quote]',  action: insertQuote },
        { label: '<i class="fas fa-code"></i>',         title: 'Code [code]',    action: function(ta){ insertWrap(ta,'[code]','[/code]'); } },
        { label: '<i class="fas fa-list-ul"></i>',      title: 'Liste [list]',   action: insertList },
        { label: '<i class="fas fa-minus"></i>',        title: 'Trennlinie [hr]',action: function(ta){ insertAt(ta,'[hr]'); } },
        { label: '<i class="fas fa-table"></i>',        title: 'Tabelle [table]',action: insertTable },
    ];

    /* ------------------------------------------------------------------
       Build toolbar DOM
    ------------------------------------------------------------------ */

    function buildToolbar(textarea) {
        var bar = document.createElement('div');
        bar.className = 'bbcode-toolbar';
        bar.setAttribute('role', 'toolbar');
        bar.setAttribute('aria-label', 'BBCode Toolbar');

        BUTTONS.forEach(function (btn) {
            if (btn.action === null) {
                /* separator */
                var sep = document.createElement('span');
                sep.className = 'bbcode-sep';
                sep.setAttribute('aria-hidden', 'true');
                bar.appendChild(sep);
                return;
            }
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'bbcode-btn';
            b.innerHTML = btn.label;
            b.title = btn.title;
            b.setAttribute('aria-label', btn.title);
            b.addEventListener('click', function (e) {
                e.preventDefault();
                btn.action(textarea);
            });
            bar.appendChild(b);
        });

        return bar;
    }

    /* ------------------------------------------------------------------
       Init: wire all toolbars on DOMContentLoaded
    ------------------------------------------------------------------ */

    function init() {
        var toolbars = document.querySelectorAll('[data-bbcode-toolbar]');
        toolbars.forEach(function (bar) {
            var targetId = bar.getAttribute('data-bbcode-toolbar');
            var textarea = document.getElementById(targetId);
            if (!textarea) { return; }
            var built = buildToolbar(textarea);
            bar.parentNode.replaceChild(built, bar);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
