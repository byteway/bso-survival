# Hooks and Filters (v2)

Laatste documentatie-update: 7 juli 2026.

## Actions
- bso_survival_metadata_error
  - Wanneer MetaDataHelper een decode/encode-gerelateerde fout tegenkomt.
  - Parameters: operation, entity, key, throwable
- bso_survival_register_scoring_methods
  - Fired na het initialiseren van de default scoremethodes (time, points, distance).
  - Doel: custom scoremethodes registreren via ScoringMethodRegistry.
  - Parameters: registry_class

- bso_survival_dashboard_widgets_init
  - Fired na het initialiseren van de default dashboard widgets.
  - Doel: custom dashboard widgets registreren via DashboardWidgetRegistry per sectie (`main`, `operations`).
  - Parameters: registry_class

  Voorbeeld:

  ```php
  add_action('bso_survival_register_scoring_methods', function () {
      // ScoringMethodRegistry::register('custom_id', new CustomScoringMethod());
  });

  add_action('bso_survival_dashboard_widgets_init', function () {
      // DashboardWidgetRegistry::register('main', new CustomMainWidget());
      // DashboardWidgetRegistry::register('operations', new CustomOperationsWidget());
  });
  ```

- admin_post_bso_survival_save_part_rule
  - WordPress admin endpoint voor opslaan van `part_rules` configuratie.
  - Gebruikt registry-validatie via `PartRuleConfiguratorService`.
  - Productie-hardening: nonce-check, capability-check (`manage_options`), whitelist-validatie voor `tiebreaker_mode` en `normalization_curve`.
- admin_post_bso_survival_save_dashboard_widgets
  - WordPress admin endpoint voor opslaan van dashboard widget-layout per event.
  - Opslag gebeurt via `DashboardWidgetLayoutService` met sanitization op section/widget-id's.
  - Productie-hardening: nonce-check en capability-check (`manage_options`).
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

## Datamodelnotitie PartRule

- `part_rules.scoring_mode` verwijst naar een geregistrede scoremethode-id.
- `part_rules.scoring_config` bevat methode-specifieke parameters (JSON in LONGTEXT).
- `part_rules.unit` bewaart de UI-eenheid (bijv. seconden, punten, meter).

## REST API

- `GET /wp-json/bso-survival/v1/dashboard-layout/{event_id}`
  - Leest event-specifieke dashboard widget-layout (`main`, `operations`).
  - Toegang: ingelogde gebruiker met `read`.
- `POST /wp-json/bso-survival/v1/dashboard-layout/{event_id}`
  - Slaat layout op voor event.
  - Body: `{ "layout": { "main": ["team_ranking"], "operations": ["message_widget"] } }`
  - Toegang: gebruiker met `manage_options`.
  - Wordt door adminpagina gebruikt voor realtime opslaan zonder volledige page reload.

## Voorbeelden

- [bso_survival_dashboard event_id="2"]
- [bso_survival_parts title="Onderdelen Event 2" event_id="2"]
- [bso_survival_teams title="Teams Event 2" event_id="2"]
- [bso_survival_event_overview title="Gecombineerd Overzicht Event 2" event_id="2"]
- [bso_survival_event_overview title="Compact Overzicht Event 2" event_id="2" compact="yes"]
- [bso_survival_event_summary title="Compact Overzicht Event 2" event_id="2"]
