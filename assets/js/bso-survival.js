(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var dashboards = document.querySelectorAll('.bso-survival-dashboard');
        dashboards.forEach(function (node) {
            node.setAttribute('data-ready', 'true');
        });
    });
})();
