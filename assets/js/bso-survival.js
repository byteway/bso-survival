(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var dashboards = document.querySelectorAll('.bso-survival-dashboard');
        dashboards.forEach(function (node) {
            node.setAttribute('data-ready', 'true');
        });

        var selects = document.querySelectorAll('[data-nav-select="parts-help"]');
        selects.forEach(function (select) {
            select.addEventListener('change', function () {
                var value = select.value || '';
                if (value !== '') {
                    window.location.href = value;
                }
            });
        });
    });
})();
