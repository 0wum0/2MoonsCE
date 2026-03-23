function mlGotoPage(page) {
    var sideInput = document.getElementById('side');
    var form      = document.getElementById('mlForm');
    if (sideInput && form) {
        sideInput.value = page;
        form.submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {

    // ── Expand / Collapse message detail rows ────────────────────────
    document.querySelectorAll('.ml-expand-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id      = btn.getAttribute('data-id');
            var detail  = document.getElementById('mlDetail' + id);
            var row     = btn.closest('tr.ml-row');
            if (!detail) return;

            var isOpen = detail.classList.contains('ml-detail-open');
            if (isOpen) {
                detail.classList.remove('ml-detail-open');
                btn.classList.remove('ml-expand-active');
                btn.innerHTML = '&#9660;';
                if (row) row.classList.remove('ml-row-active');
            } else {
                detail.classList.add('ml-detail-open');
                btn.classList.add('ml-expand-active');
                btn.innerHTML = '&#9650;';
                if (row) row.classList.add('ml-row-active');
            }
        });
    });

    // ── Simple autocomplete for sender / receiver ────────────────────
    ['sender', 'receiver'].forEach(function(fieldId) {
        var input = document.getElementById(fieldId);
        if (!input) return;

        var list = document.createElement('ul');
        list.className = 'ml-autocomplete';
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(list);

        var lastVal = '';
        input.addEventListener('input', function() {
            var val = input.value.trim();
            if (val.length < 2 || val === lastVal) {
                list.style.display = 'none';
                return;
            }
            lastVal = val;
            fetch('admin.php?page=autocomplete&term=' + encodeURIComponent(val))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    list.innerHTML = '';
                    if (!data || !data.length) { list.style.display = 'none'; return; }
                    data.forEach(function(item) {
                        var li = document.createElement('li');
                        li.textContent = typeof item === 'object' ? (item.label || item.value) : item;
                        li.addEventListener('click', function() {
                            input.value = li.textContent;
                            list.style.display = 'none';
                        });
                        list.appendChild(li);
                    });
                    list.style.display = 'block';
                })
                .catch(function() { list.style.display = 'none'; });
        });

        document.addEventListener('click', function(e) {
            if (e.target !== input) list.style.display = 'none';
        });
    });
});