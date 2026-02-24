/* sm-test Plugin – Test JS */
(function () {
    'use strict';

    function closeBanner() {
        var banner = document.getElementById('sm-test-banner');
        if (banner) {
            banner.style.transition = 'opacity 0.3s';
            banner.style.opacity = '0';
            setTimeout(function () { banner.remove(); }, 320);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('sm-test-close');
        if (btn) {
            btn.addEventListener('click', closeBanner);
        }
    });
}());
