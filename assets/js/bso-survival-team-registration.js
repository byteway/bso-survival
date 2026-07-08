(function () {
    'use strict';

    function setStatus(isSuccess, message) {
        var status = document.getElementById('bso-registration-status');
        if (!status) {
            return;
        }

        status.style.display = 'block';
        status.classList.remove('bso-survival-status-notice--published', 'bso-survival-status-notice--readonly');
        status.classList.add(isSuccess ? 'bso-survival-status-notice--published' : 'bso-survival-status-notice--readonly');
        status.querySelector('p').textContent = String(message || '');
    }

    function parseMembers(rawValue) {
        return String(rawValue || '')
            .split(/\n/)
            .map(function (name) { return name.trim(); })
            .filter(function (name) { return name !== ''; });
    }

    function generateIdempotencyKey(eventId, teamName, email) {
        var seed = [eventId, teamName, email, Date.now(), Math.random().toString(16).slice(2)].join('|');
        return 'reg-' + seed.replace(/[^a-zA-Z0-9|_-]/g, '').slice(0, 96);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('bso-team-registration-form');
        if (!form) {
            return;
        }

        var submitButton = document.getElementById('bso-registration-submit');
        var defaultSubmitLabel = submitButton ? String(submitButton.textContent || '') : '';

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var restUrl = String(form.getAttribute('data-rest-url') || '').trim();
            var nonce = String(form.getAttribute('data-registration-nonce') || '').trim();
            var eventId = Number(form.getAttribute('data-event-id') || 0);
            var teamName = String((document.getElementById('bso-registration-team-name') || {}).value || '').trim();
            var contactName = String((document.getElementById('bso-registration-contact-name') || {}).value || '').trim();
            var contactEmail = String((document.getElementById('bso-registration-contact-email') || {}).value || '').trim();
            var contactPhone = String((document.getElementById('bso-registration-contact-phone') || {}).value || '').trim();
            var membersRaw = String((document.getElementById('bso-registration-team-members') || {}).value || '');
            var teamMembers = parseMembers(membersRaw);

            if (!restUrl || !eventId) {
                setStatus(false, 'Registratieconfiguratie ontbreekt.');
                return;
            }

            if (teamMembers.length === 0) {
                setStatus(false, 'Vul minimaal 1 teamlid in.');
                return;
            }

            if (typeof window.fetch !== 'function') {
                setStatus(false, 'Deze browser ondersteunt geen fetch().');
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Inschrijven...';
            }

            window.fetch(restUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    event_id: eventId,
                    team_name: teamName,
                    contact_name: contactName,
                    contact_email: contactEmail,
                    contact_phone: contactPhone,
                    team_members: teamMembers,
                    registration_nonce: nonce,
                    idempotency_key: generateIdempotencyKey(eventId, teamName, contactEmail)
                })
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            }).then(function (json) {
                var result = json && json.result ? json.result : {};
                var status = String(result.status || 'registered');
                var counts = result.counts || {};
                var suffix = '';

                if (typeof counts.registered_teams !== 'undefined' && typeof counts.max_teams !== 'undefined' && Number(counts.max_teams) > 0) {
                    suffix = ' (' + counts.registered_teams + '/' + counts.max_teams + ')';
                }

                if (status === 'already_registered') {
                    setStatus(true, 'Dit team was al ingeschreven' + suffix + '.');
                } else {
                    setStatus(true, 'Inschrijving ontvangen' + suffix + '.');
                    form.reset();
                }
            }).catch(function (error) {
                var message = error && error.message ? error.message : 'Onbekende fout';
                setStatus(false, 'Inschrijving mislukt: ' + message);
            }).finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = defaultSubmitLabel;
                }
            });
        });
    });
})();
