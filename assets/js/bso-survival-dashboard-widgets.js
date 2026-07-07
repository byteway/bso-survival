(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var widgets = document.querySelectorAll('.bso-widget');
        widgets.forEach(function (widget, index) {
            widget.style.animationDelay = (index * 30) + 'ms';
            widget.classList.add('bso-widget-ready');
        });
    });
})();
