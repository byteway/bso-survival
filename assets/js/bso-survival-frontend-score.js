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
        var assignmentSelect = document.getElementById('bso-score-assignment-id');
        var partStatusBase = String(form.getAttribute('data-part-status-base') || '').replace(/\/$/, '');
        var restNonce = String(form.getAttribute('data-rest-nonce') || '').trim();
        var confirmWrap = document.getElementById('bso-score-confirm-wrap');
        var confirmText = document.getElementById('bso-score-confirm-text');
        var confirmButton = document.getElementById('bso-score-confirm-button');

        var selectedPartId = function () {
            if (!assignmentSelect) {
                return 0;
            }

            var selectedOption = assignmentSelect.options[assignmentSelect.selectedIndex] || null;
            if (!selectedOption) {
                return 0;
            }

            return Number(selectedOption.getAttribute('data-part-id') || 0);
        };

        var setConfirmState = function (show, text, canConfirm) {
            if (!confirmWrap || !confirmText || !confirmButton) {
                return;
            }

            confirmWrap.hidden = !show;
            confirmText.textContent = String(text || '');
            confirmButton.disabled = !canConfirm;
        };

        var renderPartStatus = function (status) {
            if (!status || !confirmWrap || !confirmText || !confirmButton) {
                return;
            }

            if (status.confirmed) {
                setConfirmState(true, 'Dit onderdeel is bevestigd. Scorewijzigingen zijn geblokkeerd voor scheidsrechters.', false);
                return;
            }

            if (status.has_ties) {
                setConfirmState(true, 'Dit onderdeel bevat nog gelijke scores (ties). Los deze eerst op voordat je bevestigt.', false);
                return;
            }

            if (!status.part_complete) {
                setConfirmState(true, 'Nog niet alle teams hebben een score. Onderdeel bevestigen kan pas na de laatste score.', false);
                return;
            }

            if (status.can_confirm) {
                setConfirmState(true, 'Alle scores zijn ingevoerd en uniek. Bevestig nu dit onderdeel om scorewijzigingen te blokkeren.', false === isBlocked);
                return;
            }

            setConfirmState(false, '', false);
        };

        var refreshPartStatus = function (partId) {
            var eventId = Number(form.getAttribute('data-event-id') || 0);
            if (!partId || !eventId || !partStatusBase || !restNonce || typeof window.fetch !== 'function') {
                setConfirmState(false, '', false);
                return Promise.resolve();
            }

            var statusUrl = partStatusBase + '/' + partId + '/status?event_id=' + eventId;
            return window.fetch(statusUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': restNonce
                }
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
                var status = json && json.result && json.result.status ? json.result.status : null;
                renderPartStatus(status);
            }).catch(function () {
                setConfirmState(false, '', false);
            });
        };

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

        if (assignmentSelect) {
            assignmentSelect.addEventListener('change', function () {
                refreshPartStatus(selectedPartId());
            });
        }

        if (confirmButton) {
            confirmButton.addEventListener('click', function () {
                var eventId = Number(form.getAttribute('data-event-id') || 0);
                var partId = selectedPartId();

                if (!eventId || !partId || !partStatusBase || !restNonce) {
                    setStatus(false, 'Bevestigen mislukt: ontbrekende configuratie.');
                    return;
                }

                var confirmed = window.confirm('Weet je zeker dat dit onderdeel definitief is? Na bevestigen kunnen scheidsrechters geen scorewijzigingen meer doen.');
                if (!confirmed) {
                    return;
                }

                confirmButton.disabled = true;
                window.fetch(partStatusBase + '/' + partId + '/confirm', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce
                    },
                    body: JSON.stringify({
                        event_id: eventId,
                        part_id: partId,
                        confirm_no_changes: true,
                        changed_by: 'frontend_jury'
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
                    setStatus(true, 'Onderdeel bevestigd. Scorewijzigingen voor scheidsrechters zijn nu geblokkeerd.');
                    if (result.finalization && result.finalization.triggered) {
                        setStatus(true, 'Alle onderdelen bevestigd. Eindstand is verwerkt en samenvattingsmail is verzonden.');
                    }

                    refreshPartStatus(partId);
                }).catch(function (error) {
                    var message = error && error.message ? error.message : 'Onbekende fout';
                    setStatus(false, 'Onderdeel bevestigen mislukt: ' + message);
                    refreshPartStatus(partId);
                }).finally(function () {
                    confirmButton.disabled = false;
                });
            });
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

                if (result.part_confirmation) {
                    renderPartStatus(result.part_confirmation);
                } else {
                    refreshPartStatus(selectedPartId());
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

        refreshPartStatus(selectedPartId());
    });

    document.addEventListener('DOMContentLoaded', function () {
        var initEditorRoots = function (selector, rowSelector, openClassName, changedByRole) {
            var editors = document.querySelectorAll(selector);
            if (!editors || !editors.length) {
                return;
            }

            editors.forEach(function (editorRoot) {
            var panel = editorRoot.querySelector('[data-score-editor-panel="1"]');
            var form = editorRoot.querySelector('[data-score-editor-form="1"]');
            var closeButton = editorRoot.querySelector('[data-score-editor-close="1"]');
            var cancelButton = editorRoot.querySelector('[data-score-editor-cancel="1"]');
            var saveButton = editorRoot.querySelector('[data-score-editor-save="1"]');
            var context = editorRoot.querySelector('[data-score-editor-context="1"]');
            var status = editorRoot.querySelector('[data-score-editor-status="1"]');
            var scoreEntryInput = editorRoot.querySelector('[data-score-editor-score-id="1"]');
            var rawInput = editorRoot.querySelector('[data-score-editor-raw="1"]');
            var bonusInput = editorRoot.querySelector('[data-score-editor-bonus="1"]');
            var jokerInput = editorRoot.querySelector('[data-score-editor-joker="1"]');
            var restBase = String(editorRoot.getAttribute('data-rest-update-base') || '').replace(/\/$/, '');
            var restNonce = String(editorRoot.getAttribute('data-rest-nonce') || '').trim();
            var eventId = Number(editorRoot.getAttribute('data-event-id') || 0);

            if (!panel || !form || !scoreEntryInput || !rawInput || !bonusInput || !jokerInput || !saveButton) {
                return;
            }

            var setPanelOpen = function (isOpen) {
                panel.hidden = !isOpen;
                panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                editorRoot.classList.toggle(openClassName, !!isOpen);
            };

            var setStatusMessage = function (message, isError) {
                if (!status) {
                    return;
                }

                if (!message) {
                    status.hidden = true;
                    status.textContent = '';
                    status.classList.remove('is-error', 'is-success');
                    return;
                }

                status.hidden = false;
                status.textContent = String(message);
                status.classList.toggle('is-error', !!isError);
                status.classList.toggle('is-success', !isError);
            };

            var openForRow = function (row) {
                var scoreEntryId = Number(row.getAttribute('data-score-entry-id') || 0);
                if (!scoreEntryId) {
                    return;
                }

                scoreEntryInput.value = String(scoreEntryId);
                rawInput.value = String(row.getAttribute('data-raw-value') || '0');
                bonusInput.value = String(row.getAttribute('data-bonus-points') || '0');
                jokerInput.checked = String(row.getAttribute('data-joker-applied') || '0') === '1';

                if (context) {
                    var labelA = String(row.getAttribute('data-team-name') || '');
                    var labelB = String(row.getAttribute('data-part-name') || '');
                    var timeslot = String(row.getAttribute('data-timeslot-label') || '-');
                    if (labelB !== '') {
                        context.textContent = 'Onderdeel: ' + labelB + ' | ' + timeslot + ' | Score ID #' + scoreEntryId;
                    } else {
                        context.textContent = 'Team: ' + (labelA || '-') + ' | ' + timeslot + ' | Score ID #' + scoreEntryId;
                    }
                }

                setStatusMessage('', false);
                setPanelOpen(true);
                rawInput.focus();
            };

            editorRoot.querySelectorAll(rowSelector + '[data-editable="1"]').forEach(function (row) {
                row.addEventListener('click', function (event) {
                    var target = event.target;
                    if (target && target.closest('a, button, input, select, textarea, label')) {
                        return;
                    }

                    openForRow(row);
                });
            });

            var closePanel = function () {
                setPanelOpen(false);
                setStatusMessage('', false);
            };

            if (closeButton) {
                closeButton.addEventListener('click', closePanel);
            }

            if (cancelButton) {
                cancelButton.addEventListener('click', closePanel);
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var scoreEntryId = Number(scoreEntryInput.value || 0);
                var rawValue = String(rawInput.value || '').trim();
                var bonusValue = String(bonusInput.value || '').trim();
                var jokerApplied = !!jokerInput.checked;

                if (!scoreEntryId) {
                    setStatusMessage('Geen geldige score geselecteerd.', true);
                    return;
                }

                if (!restBase || !restNonce || !eventId) {
                    setStatusMessage('Editor-configuratie ontbreekt.', true);
                    return;
                }

                if (rawValue === '' || Number.isNaN(Number(rawValue))) {
                    setStatusMessage('Voer een geldige numerieke ruwe score in.', true);
                    return;
                }

                if (bonusValue !== '' && (Number.isNaN(Number(bonusValue)) || Number(bonusValue) < 0)) {
                    setStatusMessage('Bonus punten moeten numeriek en >= 0 zijn.', true);
                    return;
                }

                saveButton.disabled = true;
                setStatusMessage('Opslaan...', false);

                window.fetch(restBase + '/' + scoreEntryId, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce
                    },
                    body: JSON.stringify({
                        event_id: eventId,
                        raw_value: rawValue,
                        bonus_points: bonusValue === '' ? '0' : bonusValue,
                        joker_applied: jokerApplied,
                        changed_by: changedByRole,
                        entered_by_role: changedByRole
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
                }).then(function () {
                    setStatusMessage('Score bijgewerkt. Overzicht wordt ververst...', false);
                    window.location.reload();
                }).catch(function (error) {
                    setStatusMessage('Opslaan mislukt: ' + (error && error.message ? error.message : 'Onbekende fout'), true);
                }).finally(function () {
                    saveButton.disabled = false;
                });
            });
            });
        };

        initEditorRoots('[data-part-score-editor="1"][data-can-edit="1"]', '.bso-part-score-row-clickable', 'bso-survival-part-score--editor-open', 'frontend_part_score');
        initEditorRoots('[data-team-score-editor="1"][data-can-edit="1"]', '.bso-team-score-row-clickable', 'bso-survival-team-score--editor-open', 'frontend_team_score');
    });
})();
