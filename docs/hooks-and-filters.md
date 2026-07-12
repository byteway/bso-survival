# Hooks and Filters (v2)

Laatste documentatie-update: 7 juli 2026.

## Compact Hook Index

| Domein | Hook(s) | Type | Bron |
|---|---|---|---|
| Metadata | `bso_survival_metadata_error` | action | [src/Support/MetaDataHelper.php](../src/Support/MetaDataHelper.php) |
| Registratie | `bso_survival_register_scoring_methods`, `bso_survival_dashboard_widgets_init` | action | [src/Service/ScoringMethodRegistry.php](../src/Service/ScoringMethodRegistry.php), [src/Service/DashboardWidgetRegistry.php](../src/Service/DashboardWidgetRegistry.php) |
| Renderfouten | `bso_survival_dashboard_render_error`, `bso_survival_parts_render_error`, `bso_survival_teams_render_error`, `bso_survival_event_overview_render_error`, `bso_survival_event_summary_render_error` | action | [src/Frontend/*.php](../src/Frontend) |
| Scoring filters | `bso_survival_score_normalized_points`, `bso_survival_position_proposal` | filter | [src/Service/ScoreComputationService.php](../src/Service/ScoreComputationService.php) |
| Score entry | `bso_survival_before_score_validation`, `bso_survival_score_recorded` | action | [src/Service/ScoreEntryService.php](../src/Service/ScoreEntryService.php) |
| Event status | `bso_survival_before_event_status_change`, `bso_survival_event_status_changed` | action | [src/Service/EventService.php](../src/Service/EventService.php) |
| Ranking | `bso_survival_before_ranking_refresh`, `bso_survival_ranking_updated` | action | [src/Service/RankingService.php](../src/Service/RankingService.php) |
| Certificates | `bso_survival_before_certificate_generated`, `bso_survival_certificate_generated` | action | [src/Service/CertificateService.php](../src/Service/CertificateService.php) |
| Dagafsluiting | `bso_survival_before_event_closeout`, `bso_survival_event_closed_out`, `bso_survival_before_event_publication`, `bso_survival_event_published` | action | [src/Service/EventCloseoutService.php](../src/Service/EventCloseoutService.php) |
| Audit logging | `bso_survival_before_audit_log_write`, `bso_survival_audit_log_written`, `bso_survival_audit_log_failed` | action | [src/Service/AuditLogService.php](../src/Service/AuditLogService.php) |
| Admin save endpoints | `admin_post_bso_survival_save_part_rule`, `admin_post_bso_survival_save_dashboard_widgets` | action | [src/Core/Plugin.php](../src/Core/Plugin.php) |

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

## Scoring Flow Filters

- `bso_survival_score_normalized_points`
  - Filtert de genormaliseerde score voordat die wordt teruggegeven.
  - Parameters: normalized, raw_value, part_id, scoring_rule, scoring_method, config
- `bso_survival_position_proposal`
  - Filtert de voorgestelde eindpositie-indeling voordat die wordt teruggegeven.
  - Parameters: positions, part_id, team_raw_values, scoring_rule, scoring_method, config

## Score Entry Actions

- `bso_survival_before_score_validation`
  - Fired direct before score validation starts.
  - Parameters: score_entry
- `bso_survival_score_recorded`
  - Fired after a score entry is persisted successfully.
  - Parameters: score_entry_id, assignment_id, raw_value, score_entry

## Event Status Actions

- `bso_survival_before_event_status_change`
  - Fired before an event status is updated.
  - Parameters: event_id, previous_status, new_status, event
- `bso_survival_event_status_changed`
  - Fired after an event status is updated successfully.
  - Parameters: event_id, previous_status, new_status, event

## Ranking Actions

- `bso_survival_before_ranking_refresh`
  - Fired before ranking positions are recalculated for a part.
  - Parameters: part_id, team_raw_values
- `bso_survival_ranking_updated`
  - Fired after ranking positions are recalculated for a part.
  - Parameters: part_id, positions, team_raw_values

## Certificate Actions

- `bso_survival_before_certificate_generated`
  - Fired before a certificate record is stored.
  - Parameters: payload, meta
- `bso_survival_certificate_generated`
  - Fired after a certificate record is stored successfully.
  - Parameters: certificate_id, event_id, team_id, certificate, meta

## Dagafsluiting Actions

- `bso_survival_before_event_closeout`
  - Fired before the closeout orchestrator starts status update, certificate generation and audit logging.
  - Parameters: event_id, changed_by, certificate_definitions, event
- `bso_survival_event_closed_out`
  - Fired after the closeout orchestrator has completed.
  - Parameters: event_id, result, changed_by
- `bso_survival_before_event_publication`
  - Fired before the publication flow updates the event to published status.
  - Parameters: event_id, changed_by, publication_data, event
- `bso_survival_event_published`
  - Fired after the publication flow has completed.
  - Parameters: event_id, result, changed_by

## Audit Logging Actions

- `bso_survival_before_audit_log_write`
  - Fired before an audit log row is written.
  - Parameters: payload, context
- `bso_survival_audit_log_written`
  - Fired after an audit log row is written successfully.
  - Parameters: audit_log_id, payload, audit_log, context
- `bso_survival_audit_log_failed`
  - Fired when writing an audit log row fails.
  - Parameters: payload, context, exception

## Fase 4 status

- Hook-documentatie is aanwezig
- Scoreflow gebruikt nu de filterhooks voor normalized points en position proposal
- Score entry, event status, ranking, certificate en audit logging hooks zijn toegevoegd
- Testsuite staat op 104/104 groen

## REST API

- `GET /wp-json/bso-survival/v1/dashboard-layout/{event_id}`
  - Leest event-specifieke dashboard widget-layout (`main`, `operations`).
  - Toegang: ingelogde gebruiker met `read`.
- `POST /wp-json/bso-survival/v1/dashboard-layout/{event_id}`
  - Slaat layout op voor event.
  - Body: `{ "layout": { "main": ["team_ranking"], "operations": ["message_widget"] } }`
  - Toegang: gebruiker met `manage_options`.
  - Wordt door adminpagina gebruikt voor realtime opslaan zonder volledige page reload.
- `POST /wp-json/bso-survival/v1/event-closeout/{event_id}`
  - Start de closeout-flow voor een event.
  - Body: `{ "changed_by": "wedstrijdleiding", "certificates": [{ "team_id": 5, "file_path": "/tmp/team-5.pdf" }] }`
  - Effect: zet event op `afgesloten`, registreert certificaten en schrijft auditlog.
  - Toegang: gebruiker met `manage_options` en geldige REST nonce.
- `POST /wp-json/bso-survival/v1/event-closeout/{event_id}/publish`
  - Start de publicatieflow voor een afgesloten event.
  - Body: `{ "changed_by": "wedstrijdleiding", "publication": { "headline": "Uitslag gepubliceerd" } }`
  - Effect: zet event op `gepubliceerd` en schrijft auditlog voor publicatie.
  - Toegang: gebruiker met `manage_options` en geldige REST nonce.

## Voorbeelden

- [bso_survival_dashboard event_id="2"]
- [bso_survival_parts title="Onderdelen Event 2" event_id="2"]
- [bso_survival_teams title="Teams Event 2" event_id="2"]
- [bso_survival_event_overview title="Gecombineerd Overzicht Event 2" event_id="2"]
- [bso_survival_event_overview title="Compact Overzicht Event 2" event_id="2" compact="yes"]
- [bso_survival_event_summary title="Compact Overzicht Event 2" event_id="2"]
- [bso_survival_timeslot_board title="Tijdslot overzicht Event 2" event_id="2" part_id="8"]
