(function () {
    'use strict';

    function formatResultCount(count) {
        return count === 1 ? '1 resultaat' : (count + ' resultaten');
    }

    function wireContactFinderSearch() {
        var searchInputs = document.querySelectorAll('[data-bso-contact-search]');
        searchInputs.forEach(function (input) {
            var widget = input.closest('[data-bso-contact-widget]');
            if (!widget) {
                return;
            }

            var list = widget.querySelector('[data-bso-contact-list]');
            if (!list) {
                return;
            }

            var items = list.querySelectorAll('[data-bso-contact-item]');
            var countNode = widget.querySelector('[data-bso-contact-count]');
            var emptyNode = widget.querySelector('[data-bso-contact-empty]');

            var applyFilter = function () {
                var term = String(input.value || '').trim().toLowerCase();
                var visibleCount = 0;

                items.forEach(function (item) {
                    var haystack = String(item.getAttribute('data-contact-search') || '').toLowerCase();
                    var matches = term === '' || haystack.indexOf(term) !== -1;

                    item.hidden = !matches;
                    if (matches) {
                        visibleCount++;
                    }
                });

                if (countNode) {
                    countNode.textContent = formatResultCount(visibleCount);
                }

                if (emptyNode) {
                    emptyNode.hidden = visibleCount !== 0;
                }
            };

            input.addEventListener('input', applyFilter);
            applyFilter();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var widgets = document.querySelectorAll('.bso-widget');
        widgets.forEach(function (widget, index) {
            widget.style.animationDelay = (index * 30) + 'ms';
            widget.classList.add('bso-widget-ready');
        });

        wireContactFinderSearch();
    });
})();
