(function () {
    'use strict';

    function updateSection(sectionEl) {
        var rows = Array.prototype.slice.call(sectionEl.querySelectorAll('.bso-widget-row'));
        var preview = sectionEl.querySelector('.bso-widget-preview-list');
        var warning = sectionEl.querySelector('.bso-widget-section-warning');
        var activeCount = 0;

        if (!preview) {
            return;
        }

        preview.innerHTML = '';

        rows.forEach(function (row, index) {
            var checkbox = row.querySelector('input[type="checkbox"]');
            var orderInput = row.querySelector('.bso-widget-order-input');
            var titleCell = row.children[1];
            var title = titleCell ? titleCell.textContent.split('\n')[0].trim() : row.getAttribute('data-widget-id');

            if (orderInput) {
                orderInput.value = String(index + 1);
            }

            if (checkbox && checkbox.checked) {
                activeCount += 1;
                var li = document.createElement('li');
                li.textContent = (index + 1) + '. ' + title;
                preview.appendChild(li);
            }
        });

        if (warning) {
            warning.style.display = activeCount === 0 ? 'block' : 'none';
        }

        if (preview.children.length === 0) {
            var empty = document.createElement('li');
            empty.textContent = 'Geen actieve widgets';
            preview.appendChild(empty);
        }
    }

    function setupDragDrop(sectionEl) {
        var tbody = sectionEl.querySelector('tbody');
        if (!tbody) {
            return;
        }

        var dragging = null;

        tbody.addEventListener('dragstart', function (event) {
            var row = event.target.closest('.bso-widget-row');
            if (!row) {
                return;
            }
            dragging = row;
            event.dataTransfer.effectAllowed = 'move';
        });

        tbody.addEventListener('dragover', function (event) {
            event.preventDefault();
            var row = event.target.closest('.bso-widget-row');
            if (!row || row === dragging) {
                return;
            }
            row.classList.add('bso-drag-over');
        });

        tbody.addEventListener('dragleave', function (event) {
            var row = event.target.closest('.bso-widget-row');
            if (row) {
                row.classList.remove('bso-drag-over');
            }
        });

        tbody.addEventListener('drop', function (event) {
            event.preventDefault();
            var target = event.target.closest('.bso-widget-row');
            if (!dragging || !target || dragging === target) {
                return;
            }
            target.classList.remove('bso-drag-over');
            tbody.insertBefore(dragging, target.nextSibling);
            updateSection(sectionEl);
        });

        tbody.addEventListener('dragend', function () {
            Array.prototype.forEach.call(tbody.querySelectorAll('.bso-drag-over'), function (row) {
                row.classList.remove('bso-drag-over');
            });
            dragging = null;
            updateSection(sectionEl);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var sections = document.querySelectorAll('.bso-widget-admin-section');
        Array.prototype.forEach.call(sections, function (sectionEl) {
            setupDragDrop(sectionEl);
            sectionEl.addEventListener('change', function (event) {
                if (event.target.matches('input[type="checkbox"], .bso-widget-order-input')) {
                    updateSection(sectionEl);
                }
            });
            updateSection(sectionEl);
        });
    });
})();
