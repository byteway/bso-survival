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
- teaminschrijving (frontend shortcode + REST) is operationeel binnen registratievenster
- admin inschrijvingsdashboard en registratie-capaciteitswidget zijn toegevoegd
- admin eventbeheer toegevoegd: event aanmaken/bewerken, geldig part-filteren, bestaande parts koppelen en veilig verwijderen zonder part-verlies
- admin onderdelenbeheer toegevoegd: CRUD en JSON import/export voor herbruikbare onderdelen

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
- Eventbeheer adminpagina: [src/Admin/EventAdminPage.php](src/Admin/EventAdminPage.php)
- Eventbeheer service: [src/Service/EventAdminService.php](src/Service/EventAdminService.php)
- Onderdelen adminpagina: [src/Admin/PartAdminPage.php](src/Admin/PartAdminPage.php)
- Onderdelen adminservice: [src/Service/PartAdminService.php](src/Service/PartAdminService.php)
- Eventbeheer handleiding: [docs/Admin_Eventbeheer.md](docs/Admin_Eventbeheer.md)

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
- `[bso_survival_team_registration]`
	- Attributen: `event_id`, `title`, `button_label`

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

## REST API (dashboard messages)

- GET `/wp-json/bso-survival/v1/dashboard/messages?event_id={event_id}&scope=all&page=1&per_page=20`
- POST `/wp-json/bso-survival/v1/dashboard/messages`
- PATCH `/wp-json/bso-survival/v1/dashboard/messages/{message_id}`
- DELETE `/wp-json/bso-survival/v1/dashboard/messages/{message_id}`

Voor tijdgestuurde zichtbaarheid ondersteunt create/update de velden `visible_from` en `visible_until`.
Wanneer beide zijn gezet, moet `visible_until` groter zijn dan `visible_from`.

## Handmatige smoke check (F6-03)

- Script: [tests/manual/f6-03-smoke.sh](tests/manual/f6-03-smoke.sh)
- Doel: randmomenten rond zichtvenster controleren (future, verlopen, huidig, invalid window)

Uitvoeren:

```bash
./tests/manual/f6-03-smoke.sh <event_id>
```

Of:

```bash
BSO_EVENT_ID=<event_id> ./tests/manual/f6-03-smoke.sh
```

## Runtime smoke automation (Code Server)

- Lokale secrets (niet committen): `.dev/wp-dev.env`
- Voorbeeldbestand: [ .dev/wp-dev.env.example ](.dev/wp-dev.env.example)
- Runtime shortcode smoke runner: [scripts/wp-runtime-shortcode-smoke.sh](scripts/wp-runtime-shortcode-smoke.sh)
- Runtime F6-03 visibility smoke runner: [scripts/wp-runtime-f6-03-smoke.sh](scripts/wp-runtime-f6-03-smoke.sh)
- VS Code tasks: [ .vscode/tasks.json ](.vscode/tasks.json)

Handmatig uitvoeren:

```bash
./scripts/wp-runtime-shortcode-smoke.sh
./scripts/wp-runtime-f6-03-smoke.sh
```

De task `Auto WP Runtime Smoke on Folder Open` draait automatisch bij openen van de projectmap (na toestaan van auto-tasks in VS Code).

## Admin Quickstart (eventbeheer)

1. Open `Survival -> Events`.
2. Maak een nieuw event aan met naam, datum en max teams.
3. Gebruik optioneel de vinkjes in `Nieuw event aanmaken` om direct demo teams te maken, alle onderdelen te koppelen en planning + initiële score-records te genereren.
4. Kies een bestaand event, wijzig indien nodig naam/datum/max teams en sla op.
5. Gebruik het onderdelenfilter om alleen geldig koppelbare parts snel te vinden.
6. Gesloten/gepubliceerde events zijn read-only voor inhoudelijke mutaties.
7. Verwijderen van event koppelt parts los maar verwijdert parts niet.

## Admin Quickstart (obstacle-specific rules)

1. Open `Survival -> obstacle-specific rules`.
2. Kies het event en klik `Laden`.
3. Voor actieve events zijn onderdeelregels bewerkbaar.
4. Voor gesloten/gepubliceerde/verwijderde events draait de pagina in read-only modus.
5. In read-only modus toont de pagina een nette melding en de beschikbare eventsamenvatting (headline, top-3, eindstand).

## Admin Quickstart (onderdelenbeheer)

1. Open `Survival -> Onderdelen`.
2. Maak een nieuw onderdeel aan met minimaal naam en status.
3. Leg optioneel GPS-coordinaten en uitbreidbare `meta_data` JSON vast.
4. Gebruik `Bewerken` om bestaand onderdeel inhoudelijk te wijzigen.
5. Importeer een JSON-lijst voor bulktoevoeging of exporteer de huidige set als herbruikbaar JSON-bestand.
6. Verwijderen faalt veilig als het onderdeel nog aan een actief event gekoppeld is.

Handleiding: [docs/Admin_Eventbeheer.md](docs/Admin_Eventbeheer.md)

## Gerichte tests (eventbeheer)

```bash
./scripts/run-admin-event-tests.sh
./scripts/run-admin-part-tests.sh
```

## REST API (dagafsluiting)

- POST `/wp-json/bso-survival/v1/event-closeout/{event_id}`
- POST `/wp-json/bso-survival/v1/event-closeout/{event_id}/publish`
- GET `/wp-json/bso-survival/v1/event-closeout/{event_id}/publication`

De closeout-route zet een event op `afgesloten`, registreert certificaatrecords en schrijft auditlog. De publish-route zet het event daarna op `gepubliceerd`, normaliseert publicatiepayload naar `top_3` en `final_standings`, slaat het resultaat persisted op en verwerkt notificaties via template/outbox. De publication-route levert het persisted resultaat voor operationele controle in admin.

## REST API (teaminschrijving)

- POST `/wp-json/bso-survival/v1/registrations`

Body (voorbeeld):

```json
{
	"event_id": 14,
	"team_name": "Team Kompas",
	"contact_name": "Ouder Voorbeeld",
	"contact_email": "ouder@example.test",
	"contact_phone": "0612345678",
	"team_members": ["Kind 1", "Kind 2"],
	"registration_nonce": "...",
	"idempotency_key": "reg-..."
}
```

Response bevat o.a. `registration_id`, `team_id`, `status`, en `counts.registered_teams/max_teams`.

## Admin Quickstart (inschrijvingen)

1. Plaats shortcode `[bso_survival_team_registration event_id="14"]` op een frontend-pagina.
2. Laat vrijwilliger team + teamleden invoeren en submitten.
3. Controleer in admin `BSO Rules -> Inschrijvingen` de teller `x / max_teams` en vensterstatus.
4. Controleer in dashboard de widget `Inschrijfcapaciteit` en eventuele `VOL` badge.

## Admin Quickstart (dagafsluiting)

1. Open `BSO Rules -> Event Lifecycle` in de WordPress admin.
2. Kies event, vul `Changed by`, en laad eventueel `Voorbeeld closeout`.
3. Klik `JSON valideren` en daarna `Event afsluiten (closeout)`.
4. Vul/controleer publicatievelden, laad eventueel `Voorbeeld publicatie`, controleer preview.
5. Klik `Event publiceren` en controleer `Laatste response` op `top_3`, `final_standings` en `notifications`.

Uitgebreide handleiding: [docs/Dagafsluiting_Voorbereiding.md](docs/Dagafsluiting_Voorbereiding.md)

## Admin Quickstart (score invoer)

1. Open `Survival -> Score Invoer`.
2. Kies event en klik `Laden`.
3. Klik `Initialiseer scores` om ontbrekende score-records voor alle assignments van het event vooraf aan te maken.
4. Klik op een score-rij om rechts het flip-over paneel `Score bewerken` te openen.
5. Wijzig `Ruwe score` en zet optioneel `Joker ingezet (score telt dubbel)` aan of uit.
6. Klik `Opslaan`, of klik `Annuleren`.
7. Joker is technisch afgedwongen als eenmalig per team per event; dubbele inzet wordt geblokkeerd met foutmelding.
8. Gebruik `Nieuwe score` naast `Laden` als handmatige fallback voor uitzonderingen.
9. Een extra score op hetzelfde onderdeel voor hetzelfde team is alleen toegestaan via een andere assignment/tijdslot en zolang het totaal aantal scores van alle teams gelijk blijft; anders wordt opslaan geannuleerd.

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

### 0.5.7 - 6.1.B Teaminschrijving en tellers

- Nieuwe registratie-REST endpoint en frontend shortcode-flow toegevoegd.
- Team + teamleden worden atomair opgeslagen met window/capacity-validatie.
- Admin inschrijvingspagina en dashboard capaciteit-widget (`x / max_teams`, `VOL`) toegevoegd.

### 0.5.8 - 6.1.C Template-editor en outboxbevestiging

- Registratiebevestiging-template toegevoegd met MVP veldcodes.
- Email template admin uitgebreid met templatekeuze, preview en placeholder-validatie.
- Outbox status + `last_error` zichtbaar gemaakt in admin.

### 0.5.9 - Score Invoer flip-over en initialisatie

- Score Invoer toont nu een klikbare scorelijst per event met rechter edit flip-over.
- Nieuwe knop `Initialiseer scores` maakt ontbrekende score-records voor alle event-assignments aan en slaat bestaande records over.
- Score-overzicht query gebruikt bestaande kolom `entered_by_role` (fix voor omgevingen zonder `changed_by` kolom).

### 0.5.10 - Demo-opbouw bij eventcreatie

- `Nieuw event aanmaken` bevat nu checkbox-opties voor demo teams, alle onderdelen koppelen en planning + score-records genereren.
- Demo-planning maakt automatisch timeslots en assignments aan op basis van een round-robin-achtige teamindeling.
- Initiële score-records worden direct per gegenereerde assignment aangemaakt zodat Score Invoer meteen operationeel is.

### 0.5.11 - Joker inzet in Score Invoer

- Score Invoer ondersteunt nu jokerregistratie via checkbox in het create- en edit-paneel.
- Jokergebruik wordt server-side afgedwongen als eenmalig per team per event en vastgelegd in `joker_usages`.
- `normalized_points` wordt bij jokerinzet verdubbeld en ranking-refresh gebruikt de effectieve genormaliseerde score.

## Ontwikkelcommando's

- composer install
- ./vendor/bin/phpunit
- ./vendor/bin/phpunit tests/Service/EventOverviewControllerTest.php
- ./vendor/bin/phpunit tests/Service/EventSummaryControllerTest.php
- ./vendor/bin/phpunit tests/Service/ScoringMethodRegistryTest.php
- ./vendor/bin/phpunit tests/Service/ScoringMethodsTest.php
- ./vendor/bin/phpunit tests/Service/PartRuleConfiguratorServiceTest.php
- ./vendor/bin/phpunit tests/Service/PartRuleScoringFlowTest.php

Huidige teststatus: 125/125 groen.
