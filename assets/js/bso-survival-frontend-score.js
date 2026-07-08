(function () {
    'use strict';

    function setStatus(isSuccess, message) {
        var status = document.getElementById('bso-score-status');
        if (!status) {
            return;
        }

        status.style.display = 'block';
        status.classList.remove('bso-survival-status-notice--published', 'bso-survival-status-notice--readonly');
        status.classList.add(isSuccess ? 'bso-survival-status-notice--published' : 'bso-survival-status-notice--readonly');
        status.querySelector('p').textContent = String(message || '');
    }

    function disableForm(form, disabled) {
        var fields = form.querySelectorAll('input, select, button, textarea');
        fields.forEach(function (field) {
            field.disabled = !!disabled;
        });
    }

    function extractErrorMessage(json, fallbackMessage) {
        if (json && json.error && typeof json.error.message === 'string' && json.error.message.trim() !== '') {
            return json.error.message;
        }

        if (json && typeof json.message === 'string' && json.message.trim() !== '') {
            return json.message;
        }

        return fallbackMessage;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('bso-score-form');
        if (!form) {
            return;
        }

        var isReadOnly = String(form.getAttribute('data-is-read-only') || '0') === '1';
        var isBlocked = isReadOnly;
        var hasAssignments = String(form.getAttribute('data-has-assignments') || '0') === '1';
        var submitButton = document.getElementById('bso-score-submit');
        var defaultLabel = submitButton ? String(submitButton.textContent || '') : 'Score opslaan';

        if (isReadOnly) {
            setStatus(false, 'Score-invoer is geblokkeerd omdat dit event read-only of gepubliceerd is.');
            disableForm(form, true);
            return;
        }

        if (!hasAssignments) {
            setStatus(false, 'Er zijn nog geen assignments beschikbaar voor score-invoer.');
            disableForm(form, true);
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var restUrl = String(form.getAttribute('data-rest-url') || '').trim();
            var nonce = String(form.getAttribute('data-score-nonce') || '').trim();
            var eventId = Number(form.getAttribute('data-event-id') || 0);
            var assignmentId = Number((document.getElementById('bso-score-assignment-id') || {}).value || 0);
            var rawValue = String((document.getElementById('bso-score-raw-value') || {}).value || '').trim();

            if (!restUrl || !eventId) {
                setStatus(false, 'Scoreformulierconfiguratie ontbreekt.');
                return;
            }

            if (!assignmentId) {
                setStatus(false, 'Kies eerst een assignment.');
                return;
            }

            if (rawValue === '' || Number.isNaN(Number(rawValue))) {
                setStatus(false, 'Voer een geldige numerieke score in.');
                return;
            }

            if (typeof window.fetch !== 'function') {
                setStatus(false, 'Deze browser ondersteunt geen fetch().');
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Opslaan...';
            }

            window.fetch(restUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    score_nonce: nonce,
                    event_id: eventId,
                    assignment_id: assignmentId,
                    raw_value: rawValue,
                    entered_by_role: 'frontend_jury'
                })
            }).then(function (response) {
                return response.json().catch(function () {
                    return {};
                }).then(function (json) {
                    if (!response.ok) {
                        throw new Error(extractErrorMessage(json, 'HTTP ' + response.status));
                    }

                    return json;
                });
            }).then(function (json) {
                var result = json && json.result ? json.result : {};
                var normalized = Number(result.normalized_points || 0).toFixed(2);
                setStatus(true, 'Score opgeslagen. Genormaliseerde punten: ' + normalized + '.');

                if (result.status_flags && (result.status_flags.is_read_only || result.status_flags.is_published)) {
                    isBlocked = true;
                    setStatus(false, 'Eventstatus is gewijzigd naar read-only. Verdere invoer is geblokkeerd.');
                    disableForm(form, true);
                    if (submitButton) {
                        submitButton.textContent = defaultLabel;
                    }
                    return;
                }

                var rawInput = document.getElementById('bso-score-raw-value');
                if (rawInput) {
                    rawInput.value = '';
                    rawInput.focus();
                }
            }).catch(function (error) {
                var message = error && error.message ? error.message : 'Onbekende fout';
                setStatus(false, 'Score-invoer mislukt: ' + message);
            }).finally(function () {
                if (submitButton && !isBlocked) {
                    submitButton.disabled = false;
                    submitButton.textContent = defaultLabel;
                }

                if (submitButton && isBlocked) {
                    submitButton.textContent = defaultLabel;
                }
            });
        });
    });
})();
