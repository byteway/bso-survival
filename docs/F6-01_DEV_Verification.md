# F6-01 DEV Verification Log

- Datum: 2026-07-08
- Omgeving: DEV
- Scope: F6-01 Datamodelbeslissing messages
- Referentie ADR: docs/adr/ADR-0001-message-storage-model.md

## Uitgevoerde checks

### Pass 1 - Canonical tabel en opslag
- Status: PASS
- Controle:
  - Tabel wp_bso_survival_messages bestaat
  - Minimaal 1 message-record aanwezig
  - Kolom meta_data aanwezig
- Bewijs:
  - phpMyAdmin screenshot met record in wp_bso_survival_messages

## Open checks voor F6-01 afronding

### Pass 2 - Geen dataverlies bij updateflow
- Status: OPEN
- Te controleren:
  - Aantal records voor/na plugin-deactivate-update-activate gelijk of verklaarbaar
  - Bestaande record(s) inhoudelijk intact

### Pass 3 - API smoke test messages
- Status: OPEN
- Te controleren:
  - GET dashboard messages geeft geldige response
  - Create/activate/deactivate werkt zonder regressie

### Pass 4 - Formele aftekening
- Status: OPEN
- Te controleren:
  - ADR akkoord (technisch/functioneel)
  - Backlog F6-01 van In Progress naar Done

## Notities
- Frontend dashboard wordt getoond na plugin deactivate/update/activate (reeds bevestigd).
