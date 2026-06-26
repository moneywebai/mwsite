/* Moneyweb Base — mobile menu toggle (vanilla JS) */
(function () {
    'use strict';

    function init() {
        var toggle = document.querySelector('.mw-nav-toggle');
        if (!toggle) {
            return;
        }
        var targetId = toggle.getAttribute('aria-controls');
        if (!targetId) {
            return;
        }
        var nav = document.getElementById(targetId);
        if (!nav) {
            return;
        }

        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
