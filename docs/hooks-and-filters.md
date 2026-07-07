# Hooks and Filters (v2)

Laatste documentatie-update: 7 juli 2026.

## Actions
- bso_survival_metadata_error
  - Wanneer MetaDataHelper een decode/encode-gerelateerde fout tegenkomt.
  - Parameters: operation, entity, key, throwable
- bso_survival_dashboard_render_error
  - Wanneer dashboard rendering faalt (bijvoorbeeld ongeldig event_id).
  - Parameters: message, event_id
- bso_survival_parts_render_error
  - Wanneer onderdelenlijst rendering faalt (bijvoorbeeld ongeldig event_id).
  - Parameters: message, event_id
- bso_survival_teams_render_error
  - Wanneer teamlijst rendering faalt (bijvoorbeeld ongeldig event_id).
  - Parameters: message, event_id
- bso_survival_event_overview_render_error
  - Wanneer gecombineerd eventoverzicht rendering faalt (bijvoorbeeld ongeldig event_id).
  - Parameters: message, event_id
- bso_survival_event_summary_render_error
  - Wanneer compact eventoverzicht rendering faalt (bijvoorbeeld ongeldig event_id).
  - Parameters: message, event_id

## Shortcodes
- bso_survival_dashboard
  - Rendert de basis dashboard-template.
  - Attributen: event_id, title
- bso_survival_parts
  - Rendert een read-only onderdelenlijst voor een event.
  - Attributen: event_id, title
- bso_survival_teams
  - Rendert een read-only teamlijst voor een event.
  - Attributen: event_id, title
- bso_survival_event_overview
  - Rendert een gecombineerd read-only eventoverzicht met dashboard, onderdelen en teams.
  - Attributen: event_id, title, compact (yes/no; default no)
- bso_survival_event_summary
  - Rendert een compacte read-only samenvatting met eventstatus, onderdelen en teams tellers.
  - Attributen: event_id, title

## Voorbeelden

- [bso_survival_dashboard event_id="2"]
- [bso_survival_parts title="Onderdelen Event 2" event_id="2"]
- [bso_survival_teams title="Teams Event 2" event_id="2"]
- [bso_survival_event_overview title="Gecombineerd Overzicht Event 2" event_id="2"]
- [bso_survival_event_overview title="Compact Overzicht Event 2" event_id="2" compact="yes"]
- [bso_survival_event_summary title="Compact Overzicht Event 2" event_id="2"]
