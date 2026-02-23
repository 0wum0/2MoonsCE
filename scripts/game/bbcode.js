/* BBCode toolbar helpers — shared by Forum and Alliance pages */

function bbInsert(areaId, tag) {
    const area = document.getElementById(areaId);
    if (!area) return;
    const start = area.selectionStart;
    const end   = area.selectionEnd;
    const sel   = area.value.substring(start, end);
    let open = tag, close = tag;
    if (tag.indexOf('=') !== -1) {
        open  = tag;
        close = tag.split('=')[0];
    }
    if (tag === 'url') {
        open  = 'url=https://';
        close = 'url';
    }
    const insert = '[' + open + ']' + sel + '[/' + close + ']';
    area.value = area.value.substring(0, start) + insert + area.value.substring(end);
    area.selectionStart = area.selectionEnd = start + insert.length;
    area.focus();
}

function bbInsertHr(areaId) {
    const area = document.getElementById(areaId);
    if (!area) return;
    const pos = area.selectionStart;
    area.value = area.value.substring(0, pos) + '[hr]\n' + area.value.substring(pos);
    area.selectionStart = area.selectionEnd = pos + 5;
    area.focus();
}

function bbInsertList(areaId) {
    const area = document.getElementById(areaId);
    if (!area) return;
    const pos = area.selectionStart;
    const tpl = '[list]\n[*]Eintrag 1\n[*]Eintrag 2\n[/list]\n';
    area.value = area.value.substring(0, pos) + tpl + area.value.substring(pos);
    area.selectionStart = area.selectionEnd = pos + tpl.length;
    area.focus();
}

function bbInsertOList(areaId) {
    const area = document.getElementById(areaId);
    if (!area) return;
    const pos = area.selectionStart;
    const tpl = '[list=1]\n[*]Eintrag 1\n[*]Eintrag 2\n[/list]\n';
    area.value = area.value.substring(0, pos) + tpl + area.value.substring(pos);
    area.selectionStart = area.selectionEnd = pos + tpl.length;
    area.focus();
}

function bbInsertTable(areaId) {
    const area = document.getElementById(areaId);
    if (!area) return;
    const pos = area.selectionStart;
    const tpl = '[table]\n[tr][th]Spalte 1[/th][th]Spalte 2[/th][/tr]\n[tr][td]Zelle 1[/td][td]Zelle 2[/td][/tr]\n[/table]\n';
    area.value = area.value.substring(0, pos) + tpl + area.value.substring(pos);
    area.selectionStart = area.selectionEnd = pos + tpl.length;
    area.focus();
}
