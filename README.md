# BSO Survival v2

BSO Survival v2 is de schone, uitbreidbare basis voor de volgende ontwikkelfase van de plugin.

Laatste documentatie-update: 8 juli 2026.

## Status

De codebase staat nu in een vroeg maar werkend v2-fundament:
- plugin bootstrap en activatie zijn aanwezig
- het datamodel is volledig gedefinieerd
- migratie draait bij activatie
- golden dataset en unit tests zijn beschikbaar
- repositories en services vormen de huidige leeslaag
- read-only frontend shortcodes zijn beschikbaar (dashboard, onderdelen, teams, gecombineerde varianten)
- scoremethode-architectuur voor Fase 2 is opgezet (interface, registry, defaults)
- dashboard widget-architectuur voor Fase 3 is gestart (interface, registry, sectioning, default widgets)
- dagafsluitingsflow is operationeel (closeout/publicatie via REST, admin en CLI)
- persisted publicatiebron is beschikbaar voor top-3/eindstand (incl. admin refresh)
- template/outbox notificatieketen met retries is operationeel

De basis voor dagafsluiting en publicatie is nu bruikbaar in beheerprocessen en kan verder worden uitgebreid met geavanceerde ranking- en communicatielagen.

## Huidige onderdelen

- Plugin bootstrap: [bso-survival.php](bso-survival.php)
- Activatie en migratie: [src/Core/Activator.php](src/Core/Activator.php) en [src/Database/Migrator.php](src/Database/Migrator.php)
- Datamodel: [src/Database/Schema.php](src/Database/Schema.php)
- Repositories: [src/Database/Repository](src/Database/Repository)
- Services: [src/Service](src/Service)
- Scoring contracts: [src/Contracts/ScoringMethodInterface.php](src/Contracts/ScoringMethodInterface.php)
- Scoring registry: [src/Service/ScoringMethodRegistry.php](src/Service/ScoringMethodRegistry.php)
- Scoring methods: [src/Service/ScoringMethods](src/Service/ScoringMethods)
- Dashboard widget contract: [src/Contracts/DashboardWidgetInterface.php](src/Contracts/DashboardWidgetInterface.php)
- Dashboard widget registry: [src/Service/DashboardWidgetRegistry.php](src/Service/DashboardWidgetRegistry.php)
- Dashboard widgets: [src/Widgets](src/Widgets)
- Dashboard widget assets: [assets/css/bso-survival-dashboard-widgets.css](assets/css/bso-survival-dashboard-widgets.css) en [assets/js/bso-survival-dashboard-widgets.js](assets/js/bso-survival-dashboard-widgets.js)
- Dashboard widget admin UX assets: [assets/css/bso-survival-admin-dashboard-widgets.css](assets/css/bso-survival-admin-dashboard-widgets.css) en [assets/js/bso-survival-admin-dashboard-widgets.js](assets/js/bso-survival-admin-dashboard-widgets.js)
- Dashboard widget layout admin: [src/Admin/DashboardWidgetAdminPage.php](src/Admin/DashboardWidgetAdminPage.php)
- Dashboard widget layout service: [src/Service/DashboardWidgetLayoutService.php](src/Service/DashboardWidgetLayoutService.php)
- Dashboard widget layout REST controller: [src/Api/DashboardWidgetLayoutRestController.php](src/Api/DashboardWidgetLayoutRestController.php)
- PartRule configuratie-service: [src/Service/PartRuleConfiguratorService.php](src/Service/PartRuleConfiguratorService.php)
- Scoreberekening op PartRule: [src/Service/ScoreComputationService.php](src/Service/ScoreComputationService.php)
- Admin configuratiepagina: [src/Admin/PartRuleAdminPage.php](src/Admin/PartRuleAdminPage.php)
- Frontend controllers: [src/Frontend](src/Frontend)
- Frontend templates: [templates](templates)
- Golden dataset: [tests/Support/GoldenDataset.php](tests/Support/GoldenDataset.php)
- Regressietests: [tests/Support](tests/Support) en [tests/Service](tests/Service)
- Dagafsluitingsvoorbereiding: [docs/Dagafsluiting_Voorbereiding.md](docs/Dagafsluiting_Voorbereiding.md)
- Hooks en shortcodes: [docs/hooks-and-filters.md](docs/hooks-and-filters.md)
- Dagafsluitingsservice: [src/Service/EventCloseoutService.php](src/Service/EventCloseoutService.php)
- Dagafsluiting adminpagina: [src/Admin/EventLifecycleAdminPage.php](src/Admin/EventLifecycleAdminPage.php)
- Dagafsluiting CLI-command: [src/Core/Cli/EventLifecycleCommand.php](src/Core/Cli/EventLifecycleCommand.php)
- Publicatienotificatieservice: [src/Service/PublicationNotificationService.php](src/Service/PublicationNotificationService.php)

## Frontend shortcodes (actueel)

- `[bso_survival_dashboard]`
	- Attributen: `event_id`, `title`
- `[bso_survival_parts]`
	- Attributen: `event_id`, `title`
- `[bso_survival_teams]`
	- Attributen: `event_id`, `title`
- `[bso_survival_event_overview]`
	- Attributen: `event_id`, `title`, `compact` (`yes`/`no`, default `no`)
- `[bso_survival_event_summary]`
	- Attributen: `event_id`, `title`

Voorbeeld:

```text
[bso_survival_event_overview title="Gecombineerd Overzicht Event 2" event_id="2" compact="yes"]
```

## Hook index

Een compacte index van de belangrijkste actions en filters staat ook in [docs/hooks-and-filters.md](docs/hooks-and-filters.md).

| Domein | Hooks |
|---|---|
| Metadata | `bso_survival_metadata_error` |
| Registratie | `bso_survival_register_scoring_methods`, `bso_survival_dashboard_widgets_init` |
| Renderfouten | `bso_survival_dashboard_render_error`, `bso_survival_parts_render_error`, `bso_survival_teams_render_error`, `bso_survival_event_overview_render_error`, `bso_survival_event_summary_render_error` |
| Scoring | `bso_survival_score_normalized_points`, `bso_survival_position_proposal` |
| Score-invoer | `bso_survival_before_score_validation`, `bso_survival_score_recorded` |
| Eventstatus | `bso_survival_before_event_status_change`, `bso_survival_event_status_changed` |
| Ranking | `bso_survival_before_ranking_refresh`, `bso_survival_ranking_updated` |
| Certificaten | `bso_survival_before_certificate_generated`, `bso_survival_certificate_generated` |
| Auditlog | `bso_survival_before_audit_log_write`, `bso_survival_audit_log_written`, `bso_survival_audit_log_failed` |

## Wat nog niet is uitgewerkt

- automatische eindstandberekening direct vanuit rankingservice (zonder handmatige standings-input)
- operationele rapportage op notificatiedelivery en foutpercentages

## REST API (dashboard layout)

- GET `/wp-json/bso-survival/v1/dashboard-layout/{event_id}`
- POST `/wp-json/bso-survival/v1/dashboard-layout/{event_id}` met body:

```json
{
	"layout": {
		"main": ["team_ranking"],
		"operations": ["message_widget"]
	}
}
```

Adminpagina gebruikt dezelfde endpoint voor realtime opslaan zonder page reload en toont inline succes/foutmeldingen.

## REST API (dagafsluiting)

- POST `/wp-json/bso-survival/v1/event-closeout/{event_id}`
- POST `/wp-json/bso-survival/v1/event-closeout/{event_id}/publish`
- GET `/wp-json/bso-survival/v1/event-closeout/{event_id}/publication`

De closeout-route zet een event op `afgesloten`, registreert certificaatrecords en schrijft auditlog. De publish-route zet het event daarna op `gepubliceerd`, normaliseert publicatiepayload naar `top_3` en `final_standings`, slaat het resultaat persisted op en verwerkt notificaties via template/outbox. De publication-route levert het persisted resultaat voor operationele controle in admin.

## Admin Quickstart (dagafsluiting)

1. Open `BSO Rules -> Event Lifecycle` in de WordPress admin.
2. Kies event, vul `Changed by`, en laad eventueel `Voorbeeld closeout`.
3. Klik `JSON valideren` en daarna `Event afsluiten (closeout)`.
4. Vul/controleer publicatievelden, laad eventueel `Voorbeeld publicatie`, controleer preview.
5. Klik `Event publiceren` en controleer `Laatste response` op `top_3`, `final_standings` en `notifications`.

Uitgebreide handleiding: [docs/Dagafsluiting_Voorbereiding.md](docs/Dagafsluiting_Voorbereiding.md)

## CLI Quickstart (dagafsluiting)

- `wp bso-survival lifecycle --phase=closeout --event_id=14 --changed_by=wedstrijdleiding --certificates='[{"team_id":5,"file_path":"/tmp/team-5.pdf"}]'`
- `wp bso-survival lifecycle --phase=publish --event_id=14 --changed_by=wedstrijdleiding --publication='{"headline":"Uitslag gepubliceerd","standings":[{"rank":1,"team_id":11,"team_name":"Team Rood","points":98.5}],"recipients":["coach@example.test"]}'`

## Release notes 0.5.x

### 0.5.0 - Lifecycle basis operationeel

- Event closeout/publicatie routes actief en gekoppeld aan service-orchestratie.
- Frontend read-only/publicatiegedrag na statusovergang bevestigd.
- Audit logging op closeout en publicatie volledig gekoppeld.

### 0.5.1 - Bedieningslaag toegevoegd

- Nieuwe adminpagina `Event Lifecycle` toegevoegd voor closeout/publicatie-acties.
- Nieuwe CLI command `wp bso-survival lifecycle` toegevoegd voor beheer/automation.
- Realtime response feedback in admin voor snellere operationele controle.

### 0.5.2 - Publicatiecontract geconcretiseerd

- Publicatiepayload gestandaardiseerd met `headline`, `published_at`, `top_3`, `final_standings`, `recipients`.
- Top-3 en volledige eindstand komen nu uniform terug in publicatieresponse.
- Tests uitgebreid op payload-structuur en publicatieresultaat.

### 0.5.3 - Notificatiebasis na publicatie

- Publicatienotificatieservice toegevoegd met recipient-normalisatie.
- Verzendsamenvatting (`sent_count`, `failed_count`, `sent_to`, `failed_to`) opgenomen in result payload.
- Hooks voor notificatiefase toegevoegd voor verdere uitbouw (templates/outbox/retry).

### 0.5.4 - Robuuste template/outbox keten

- Email templatebeheer toegevoegd in admin.
- Outbox/retry processor gekoppeld met retry-schema en foutafhandeling.
- End-to-end notificatiepipeline-tests toegevoegd.

### 0.5.5 - Persisted publicatiebron + widgetkoppeling

- Persisted `event_publications` bron toegevoegd voor publicatieresultaten.
- Team ranking widget leest nu primair uit persisted `final_standings`.
- Dashboard-overview uitgebreid met publicatiestatus/count-velden.

### 0.5.6 - Lifecycle admin ververstroom

- Lifecycle admin toont persisted eindstand inclusief top-3 en raw payload.
- Handmatige refresh en auto-refresh na publish toegevoegd.
- UX verbeterd met busy-labels, spinner, en specifieke statusmeldingen.

## Ontwikkelcommando's

- composer install
- ./vendor/bin/phpunit
- ./vendor/bin/phpunit tests/Service/EventOverviewControllerTest.php
- ./vendor/bin/phpunit tests/Service/EventSummaryControllerTest.php
- ./vendor/bin/phpunit tests/Service/ScoringMethodRegistryTest.php
- ./vendor/bin/phpunit tests/Service/ScoringMethodsTest.php
- ./vendor/bin/phpunit tests/Service/PartRuleConfiguratorServiceTest.php
- ./vendor/bin/phpunit tests/Service/PartRuleScoringFlowTest.php

Huidige teststatus: 118/118 groen.
