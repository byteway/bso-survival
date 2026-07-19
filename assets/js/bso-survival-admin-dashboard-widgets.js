(function () {
    'use strict';

    function showStatus(form, isSuccess, message) {
        var status = form.querySelector('.bso-widget-save-status');
        if (!status) {
            return;
        }

        status.style.display = 'block';
        status.classList.remove('notice-success', 'notice-error', 'is-success', 'is-error');
        status.classList.add(isSuccess ? 'notice-success' : 'notice-error');
        status.classList.add(isSuccess ? 'is-success' : 'is-error');
        status.querySelector('p').textContent = message;
    }

    function collectLayout(form) {
        var layout = {};
        var widths = {};
        var navigation = {
            parts_help_page_id: 0,
            team_score_page_id: 0
        };

        Array.prototype.forEach.call(form.querySelectorAll('.bso-widget-admin-section'), function (sectionEl) {
            var section = sectionEl.getAttribute('data-section');
            var rows = sectionEl.querySelectorAll('.bso-widget-row');
            layout[section] = [];
            widths[section] = {};

            Array.prototype.forEach.call(rows, function (row) {
                var checkbox = row.querySelector('input[type="checkbox"]');
                var widthSelect = row.querySelector('.bso-widget-width-select');
                if (checkbox && checkbox.checked) {
                    layout[section].push(row.getAttribute('data-widget-id'));
                }

                if (widthSelect) {
                    widths[section][row.getAttribute('data-widget-id')] = widthSelect.value;
                }
            });
        });

        var partsPageSelect = form.querySelector('select[name="navigation[parts_help_page_id]"]');
        if (partsPageSelect) {
            navigation.parts_help_page_id = parseInt(partsPageSelect.value, 10) || 0;
        }

        var teamsPageSelect = form.querySelector('select[name="navigation[team_score_page_id]"]');
        if (teamsPageSelect) {
            navigation.team_score_page_id = parseInt(teamsPageSelect.value, 10) || 0;
        }

        return {
            layout: layout,
            widths: widths,
            navigation: navigation
        };
    }

    function saveViaRest(form) {
        var eventIdInput = form.querySelector('input[name="event_id"]');
        var restBase = form.getAttribute('data-rest-base') || '';
        var restNonce = form.getAttribute('data-rest-nonce') || '';
        var submitButton = form.querySelector('button[type="submit"]');

        if (!eventIdInput || !restBase || !restNonce || typeof window.fetch !== 'function') {
            return false;
        }

        var eventId = String(eventIdInput.value || '').trim();
        if (eventId === '') {
            showStatus(form, false, 'Opslaan mislukt: event_id ontbreekt.');
            return true;
        }

        var payload = collectLayout(form);
        var endpoint = restBase.replace(/\/$/, '') + '/' + encodeURIComponent(eventId);

        if (submitButton) {
            submitButton.disabled = true;
        }

        window.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': restNonce
            },
            body: JSON.stringify({
                layout: payload.layout,
                widths: payload.widths,
                navigation: payload.navigation
            })
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function () {
            showStatus(form, true, 'Dashboardlayout realtime opgeslagen.');
        }).catch(function (error) {
            var details = error && error.message ? ' (' + error.message + ')' : '';
            showStatus(form, false, 'Realtime opslaan mislukt' + details + '.');
        }).finally(function () {
            if (submitButton) {
                submitButton.disabled = false;
            }
        });

        return true;
    }

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
            var widthSelect = row.querySelector('.bso-widget-width-select');
            var titleCell = row.children[1];
            var title = titleCell ? titleCell.textContent.split('\n')[0].trim() : row.getAttribute('data-widget-id');
            var width = widthSelect ? widthSelect.value : '';

            if (orderInput) {
                orderInput.value = String(index + 1);
            }

            if (checkbox && checkbox.checked) {
                activeCount += 1;
                var li = document.createElement('li');
                li.textContent = (index + 1) + '. ' + title + (width ? ' - ' + width : '');
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
        var form = document.getElementById('bso-dashboard-widget-layout-form');
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

        if (form) {
            form.addEventListener('submit', function (event) {
                if (saveViaRest(form)) {
                    event.preventDefault();
                }
            });
        }
    });
})();
