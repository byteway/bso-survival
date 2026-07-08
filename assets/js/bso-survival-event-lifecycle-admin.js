(function () {
    'use strict';

    function getRoot() {
        return document.getElementById('bso-event-lifecycle-admin');
    }

    function parseJsonInput(value, fallback) {
        var trimmed = String(value || '').trim();
        if (trimmed === '') {
            return fallback;
        }

        try {
            var parsed = JSON.parse(trimmed);
            return parsed;
        } catch (error) {
            return null;
        }
    }

    function updateStatus(isSuccess, message) {
        var status = document.getElementById('bso-lifecycle-status');
        if (!status) {
            return;
        }

        status.style.display = 'block';
        status.classList.remove('notice-success', 'notice-error');
        status.classList.add(isSuccess ? 'notice-success' : 'notice-error');
        status.querySelector('p').textContent = message;
    }

    function updateResponse(payload) {
        var response = document.getElementById('bso-lifecycle-response');
        if (!response) {
            return;
        }

        response.textContent = JSON.stringify(payload, null, 2);
    }

    function setBusy(button, isBusy) {
        if (!button) {
            return;
        }

        button.disabled = isBusy;
    }

    function ensureSpinnerStyles() {
        if (document.getElementById('bso-lifecycle-spinner-styles')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'bso-lifecycle-spinner-styles';
        style.textContent = '' +
            '.bso-lifecycle-btn-spinner{' +
            'display:inline-block;' +
            'width:12px;' +
            'height:12px;' +
            'margin-left:6px;' +
            'border:2px solid currentColor;' +
            'border-right-color:transparent;' +
            'border-radius:50%;' +
            'vertical-align:-2px;' +
            'opacity:.7;' +
            'animation:bso-lifecycle-spin .7s linear infinite;' +
            '}' +
            '@keyframes bso-lifecycle-spin{to{transform:rotate(360deg);}}';
        document.head.appendChild(style);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function setButtonBusyLabel(button, label, showSpinner) {
        if (!button || !label) {
            return null;
        }

        var originalHtml = String(button.innerHTML || '');
        if (showSpinner) {
            ensureSpinnerStyles();
            button.innerHTML = '<span class="bso-lifecycle-btn-label">' + escapeHtml(label) + '</span><span class="bso-lifecycle-btn-spinner" aria-hidden="true"></span>';
        } else {
            button.textContent = label;
        }

        return originalHtml;
    }

    function updatePersistedPanel(publication) {
        var emptyEl = document.getElementById('bso-lifecycle-persisted-empty');
        var contentEl = document.getElementById('bso-lifecycle-persisted-content');
        var headlineEl = document.getElementById('bso-lifecycle-persisted-headline');
        var publishedAtEl = document.getElementById('bso-lifecycle-persisted-published-at');
        var countEl = document.getElementById('bso-lifecycle-persisted-count');
        var top3El = document.getElementById('bso-lifecycle-persisted-top3');
        var rawEl = document.getElementById('bso-lifecycle-persisted-raw');

        if (rawEl) {
            rawEl.textContent = JSON.stringify(publication || null, null, 2);
        }

        if (!publication || typeof publication !== 'object') {
            if (emptyEl) {
                emptyEl.style.display = 'block';
            }
            if (contentEl) {
                contentEl.style.display = 'none';
            }
            if (top3El) {
                top3El.innerHTML = '';
            }
            return;
        }

        if (emptyEl) {
            emptyEl.style.display = 'none';
        }
        if (contentEl) {
            contentEl.style.display = 'block';
        }
        if (headlineEl) {
            headlineEl.textContent = String(publication.headline || '-');
        }
        if (publishedAtEl) {
            publishedAtEl.textContent = String(publication.published_at || '-');
        }

        var finalStandings = Array.isArray(publication.final_standings) ? publication.final_standings : [];
        if (countEl) {
            countEl.textContent = String(finalStandings.length);
        }

        if (top3El) {
            top3El.innerHTML = '';
            var top3 = Array.isArray(publication.top_3) ? publication.top_3 : [];
            if (top3.length === 0) {
                top3El.innerHTML = '<li>Geen top-3 opgeslagen.</li>';
            } else {
                top3.forEach(function (item) {
                    if (!item || typeof item !== 'object') {
                        return;
                    }

                    var rank = Number(item.rank || 0);
                    var teamName = String(item.team_name || 'Onbekend team');
                    var points = Number(item.points || 0);
                    var li = document.createElement('li');
                    li.innerHTML = '#' + escapeHtml(rank) + ' ' + escapeHtml(teamName) + ' (' + escapeHtml(points.toFixed(2)) + ' pt)';
                    top3El.appendChild(li);
                });
            }
        }
    }

    function fetchPersistedPublication(eventId, restBase, nonce, actionButton, options) {
        var opts = options && typeof options === 'object' ? options : {};
        var originalHtml = null;
        if (typeof window.fetch !== 'function') {
            updateStatus(false, 'Deze browser ondersteunt geen fetch().');
            return Promise.reject(new Error('fetch_not_supported'));
        }

        if (actionButton && opts.busyLabel) {
            originalHtml = setButtonBusyLabel(actionButton, String(opts.busyLabel), !!opts.busySpinner);
        }

        setBusy(actionButton, true);

        var endpoint = restBase.replace(/\/$/, '') + '/' + encodeURIComponent(eventId) + '/publication';
        return window.fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': nonce
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function (json) {
            updatePersistedPanel(json.publication || null);
            if (!opts.silent) {
                updateStatus(true, String(opts.successMessage || 'Persisted result ververst.'));
            }
            return json;
        }).catch(function (error) {
            var message = error && error.message ? error.message : 'Onbekende fout';
            if (!opts.silent) {
                updateStatus(false, String(opts.errorPrefix || 'Verversen mislukt') + ': ' + message);
            }
            throw error;
        }).finally(function () {
            setBusy(actionButton, false);
            if (actionButton && originalHtml !== null) {
                actionButton.innerHTML = originalHtml;
            }
        });
    }

    function collectRecipients() {
        var recipientsInput = document.getElementById('bso-lifecycle-recipients');
        var sendNotifications = document.getElementById('bso-lifecycle-send-notifications');
        var value = recipientsInput ? String(recipientsInput.value || '') : '';

        if (sendNotifications && !sendNotifications.checked) {
            return [];
        }

        return value
            .split(/[,;\n]/)
            .map(function (item) { return item.trim(); })
            .filter(function (item) { return item !== ''; });
    }

    function buildCloseoutPayload() {
        var changedByInput = document.getElementById('bso-lifecycle-changed-by');
        var certificatesInput = document.getElementById('bso-lifecycle-certificates');
        var certificates = parseJsonInput(certificatesInput ? certificatesInput.value : '[]', []);

        if (!Array.isArray(certificates)) {
            return { error: 'Certificates JSON moet een array zijn.' };
        }

        return {
            changed_by: String(changedByInput ? changedByInput.value : '').trim(),
            certificates: certificates
        };
    }

    function buildPublicationPayload() {
        var changedByInput = document.getElementById('bso-lifecycle-changed-by');
        var headlineInput = document.getElementById('bso-lifecycle-headline');
        var standingsInput = document.getElementById('bso-lifecycle-standings');
        var standings = parseJsonInput(standingsInput ? standingsInput.value : '[]', []);

        if (!Array.isArray(standings)) {
            return { error: 'Standings JSON moet een array zijn.' };
        }

        return {
            changed_by: String(changedByInput ? changedByInput.value : '').trim(),
            publication: {
                headline: String(headlineInput ? headlineInput.value : '').trim(),
                published_at: String((document.getElementById('bso-lifecycle-published-at') || {}).value || '').trim(),
                standings: standings,
                recipients: collectRecipients()
            }
        };
    }

    function getEventDisplayName() {
        var root = getRoot();
        if (!root) {
            return 'event';
        }

        return String(root.getAttribute('data-event-name') || '').trim() || 'event';
    }

    function fillCloseoutExample() {
        var certificatesInput = document.getElementById('bso-lifecycle-certificates');
        if (!certificatesInput) {
            return;
        }

        var example = [
            {
                team_id: 101,
                file_path: '/exports/certificates/team-101.pdf',
                meta: { position: 1, category: 'overall' }
            },
            {
                team_id: 102,
                file_path: '/exports/certificates/team-102.pdf',
                meta: { position: 2, category: 'overall' }
            }
        ];

        certificatesInput.value = JSON.stringify(example, null, 2);
        updateStatus(true, 'Voorbeeld closeout payload geladen.');
    }

    function fillPublicationExample() {
        var headlineInput = document.getElementById('bso-lifecycle-headline');
        var standingsInput = document.getElementById('bso-lifecycle-standings');
        var recipientsInput = document.getElementById('bso-lifecycle-recipients');
        var publishedAtInput = document.getElementById('bso-lifecycle-published-at');
        var eventName = getEventDisplayName();

        if (headlineInput) {
            headlineInput.value = 'Eindstand gepubliceerd - ' + eventName;
        }

        if (publishedAtInput && !String(publishedAtInput.value || '').trim()) {
            publishedAtInput.value = new Date().toISOString();
        }

        if (standingsInput) {
            standingsInput.value = JSON.stringify([
                { rank: 1, team_id: 201, team_name: 'Team Delta', points: 98.5 },
                { rank: 2, team_id: 202, team_name: 'Team Echo', points: 95.25 },
                { rank: 3, team_id: 203, team_name: 'Team Foxtrot', points: 93.0 },
                { rank: 4, team_id: 204, team_name: 'Team Golf', points: 90.75 }
            ], null, 2);
        }

        if (recipientsInput && !String(recipientsInput.value || '').trim()) {
            recipientsInput.value = 'coach@example.test, leiding@example.test';
        }

        refreshPublicationPreview();
        updateStatus(true, 'Voorbeeld publicatie payload geladen.');
    }

    function clearJsonFields() {
        var certificatesInput = document.getElementById('bso-lifecycle-certificates');
        var standingsInput = document.getElementById('bso-lifecycle-standings');

        if (certificatesInput) {
            certificatesInput.value = '[]';
        }

        if (standingsInput) {
            standingsInput.value = '[]';
        }

        refreshPublicationPreview();
        updateStatus(true, 'JSON velden zijn leeggemaakt.');
    }

    function validateJsonInputs() {
        var certificatesInput = document.getElementById('bso-lifecycle-certificates');
        var standingsInput = document.getElementById('bso-lifecycle-standings');

        var certificates = parseJsonInput(certificatesInput ? certificatesInput.value : '[]', []);
        if (!Array.isArray(certificates)) {
            updateStatus(false, 'Certificates JSON is ongeldig of geen array.');
            return false;
        }

        var standings = parseJsonInput(standingsInput ? standingsInput.value : '[]', []);
        if (!Array.isArray(standings)) {
            updateStatus(false, 'Standings JSON is ongeldig of geen array.');
            return false;
        }

        updateStatus(true, 'JSON validatie geslaagd.');
        return true;
    }

    function normalizeStandingsForPreview() {
        var standingsInput = document.getElementById('bso-lifecycle-standings');
        var standings = parseJsonInput(standingsInput ? standingsInput.value : '[]', []);
        if (!Array.isArray(standings)) {
            return [];
        }

        var normalized = standings
            .filter(function (item) { return item && typeof item === 'object'; })
            .map(function (item, index) {
                var rank = Number(item.rank);
                if (!Number.isFinite(rank) || rank <= 0) {
                    rank = index + 1;
                }

                return {
                    rank: rank,
                    team_name: String(item.team_name || 'Onbekend team'),
                    points: Number(item.points || 0)
                };
            });

        normalized.sort(function (a, b) {
            if (a.rank === b.rank) {
                return b.points - a.points;
            }
            return a.rank - b.rank;
        });

        return normalized;
    }

    function refreshPublicationPreview() {
        var countEl = document.getElementById('bso-lifecycle-preview-count');
        var top3El = document.getElementById('bso-lifecycle-preview-top3');
        if (!countEl || !top3El) {
            return;
        }

        var standings = normalizeStandingsForPreview();
        countEl.textContent = String(standings.length);
        top3El.innerHTML = '';

        if (standings.length === 0) {
            var emptyItem = document.createElement('li');
            emptyItem.textContent = 'Nog geen data';
            top3El.appendChild(emptyItem);
            return;
        }

        standings.slice(0, 3).forEach(function (item) {
            var li = document.createElement('li');
            li.textContent = '#' + item.rank + ' ' + item.team_name + ' (' + item.points.toFixed(2) + ' pt)';
            top3El.appendChild(li);
        });
    }

    function submitToEndpoint(endpoint, payload, actionButton, onSuccess, options) {
        var opts = options && typeof options === 'object' ? options : {};
        var originalHtml = null;
        var root = getRoot();
        var nonce = root ? String(root.getAttribute('data-rest-nonce') || '') : '';

        if (payload.error) {
            updateStatus(false, payload.error);
            return;
        }

        if (!payload.changed_by) {
            updateStatus(false, 'changed_by is verplicht.');
            return;
        }

        if (typeof window.fetch !== 'function') {
            updateStatus(false, 'Deze browser ondersteunt geen fetch().');
            return;
        }

        if (actionButton && opts.busyLabel) {
            originalHtml = setButtonBusyLabel(actionButton, String(opts.busyLabel), !!opts.busySpinner);
        }

        setBusy(actionButton, true);

        window.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function (json) {
            updateResponse(json);
            if (typeof onSuccess === 'function') {
                return Promise.resolve(onSuccess(json)).then(function () {
                    updateStatus(true, String(opts.successMessage || 'Actie succesvol uitgevoerd.'));
                    return json;
                });
            }

            updateStatus(true, String(opts.successMessage || 'Actie succesvol uitgevoerd.'));
            return json;
        }).catch(function (error) {
            var message = error && error.message ? error.message : 'Onbekende fout';
            updateStatus(false, String(opts.errorPrefix || 'Actie mislukt') + ': ' + message);
        }).finally(function () {
            setBusy(actionButton, false);
            if (actionButton && originalHtml !== null) {
                actionButton.innerHTML = originalHtml;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = getRoot();
        if (!root) {
            return;
        }

        var eventId = String(root.getAttribute('data-event-id') || '').trim();
        var restBase = String(root.getAttribute('data-rest-base') || '').trim();
        var nonce = String(root.getAttribute('data-rest-nonce') || '').trim();
        var closeoutButton = document.getElementById('bso-lifecycle-closeout');
        var publishButton = document.getElementById('bso-lifecycle-publish');
        var refreshPersistedButton = document.getElementById('bso-lifecycle-refresh-persisted');
        var validateButton = document.getElementById('bso-lifecycle-validate-json');
        var fillCloseoutButton = document.getElementById('bso-lifecycle-fill-closeout');
        var fillPublicationButton = document.getElementById('bso-lifecycle-fill-publication');
        var clearJsonButton = document.getElementById('bso-lifecycle-clear-json');
        var standingsInput = document.getElementById('bso-lifecycle-standings');

        if (!eventId || !restBase) {
            updateStatus(false, 'REST configuratie ontbreekt.');
            return;
        }

        if (refreshPersistedButton) {
            refreshPersistedButton.addEventListener('click', function () {
                fetchPersistedPublication(eventId, restBase, nonce, refreshPersistedButton, {
                    busyLabel: 'Verversen...',
                    busySpinner: true,
                    successMessage: 'Persisted result handmatig ververst.',
                    errorPrefix: 'Handmatige refresh mislukt'
                });
            });
        }

        if (fillCloseoutButton) {
            fillCloseoutButton.addEventListener('click', fillCloseoutExample);
        }

        if (fillPublicationButton) {
            fillPublicationButton.addEventListener('click', fillPublicationExample);
        }

        if (clearJsonButton) {
            clearJsonButton.addEventListener('click', clearJsonFields);
        }

        if (validateButton) {
            validateButton.addEventListener('click', function () {
                validateJsonInputs();
                refreshPublicationPreview();
            });
        }

        if (standingsInput) {
            standingsInput.addEventListener('input', refreshPublicationPreview);
        }

        refreshPublicationPreview();

        if (closeoutButton) {
            closeoutButton.addEventListener('click', function () {
                if (!validateJsonInputs()) {
                    return;
                }

                if (!window.confirm('Weet je zeker dat je dit event wilt afsluiten?')) {
                    return;
                }

                var endpoint = restBase.replace(/\/$/, '') + '/' + encodeURIComponent(eventId);
                submitToEndpoint(endpoint, buildCloseoutPayload(), closeoutButton);
            });
        }

        if (publishButton) {
            publishButton.addEventListener('click', function () {
                if (!validateJsonInputs()) {
                    return;
                }

                if (!window.confirm('Weet je zeker dat je dit event wilt publiceren?')) {
                    return;
                }

                var endpoint = restBase.replace(/\/$/, '') + '/' + encodeURIComponent(eventId) + '/publish';
                submitToEndpoint(
                    endpoint,
                    buildPublicationPayload(),
                    publishButton,
                    function () {
                        return fetchPersistedPublication(eventId, restBase, nonce, refreshPersistedButton, {
                            silent: true,
                            busyLabel: 'Verversen...',
                            busySpinner: true,
                            errorPrefix: 'Automatische refresh na publicatie mislukt'
                        });
                    },
                    {
                        busyLabel: 'Publiceren...',
                        busySpinner: true,
                        successMessage: 'Event gepubliceerd en persisted result automatisch ververst.'
                    }
                );
            });
        }
    });
})();
