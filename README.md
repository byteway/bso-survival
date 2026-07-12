# BSO Survival v2

BSO Survival v2 is de schone, uitbreidbare basis voor de volgende ontwikkelfase van de plugin.

Laatste documentatie-update: 10 juli 2026.

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
- demo-simulatie voor scoreverloop via WP-CLI is beschikbaar en idempotent (update-only op bestaande score-records)
- score-invoer uitgebreid met numeriek bonusveld per score-entry, inclusief sortering in admin en shortcode-tabellen
- tie-resolutie bij gelijke ruwe score gebruikt bonuspunten als eerste tie-break
- admin toegang en rollen toegevoegd: gebruiker-override per WordPress account voor settings/score/meldingen rechten

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
- Widgetbreedte per dashboard widget is configureerbaar in de adminlayout met de opties `1/5`, `1/4`, `3/4` en `1` (hele breedte).
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
- Demo-score CLI-command: [src/Core/Cli/SeedDemoScoresCommand.php](src/Core/Cli/SeedDemoScoresCommand.php)
- Publicatienotificatieservice: [src/Service/PublicationNotificationService.php](src/Service/PublicationNotificationService.php)
- Eventbeheer adminpagina: [src/Admin/EventAdminPage.php](src/Admin/EventAdminPage.php)
- Eventbeheer service: [src/Service/EventAdminService.php](src/Service/EventAdminService.php)
- Toegang en rollen adminpagina: [src/Admin/AccessAdminPage.php](src/Admin/AccessAdminPage.php)
- Capabilities helper: [src/Support/Capabilities.php](src/Support/Capabilities.php)
- Onderdelen adminpagina: [src/Admin/PartAdminPage.php](src/Admin/PartAdminPage.php)
- Onderdelen adminservice: [src/Service/PartAdminService.php](src/Service/PartAdminService.php)
- Eventbeheer handleiding: [docs/Admin_Eventbeheer.md](docs/Admin_Eventbeheer.md)
- Beheer runbook demo-simulatie: [docs/Runbook_Beheer_Demo_Simulatie.md](docs/Runbook_Beheer_Demo_Simulatie.md)

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
- `[bso_survival_team_score]`
	- Attributen: `event_id`, `team_id`, `title`
	- Zonder `team_id` wordt automatisch het eerste team van het event gekozen; bovenin staat een team-combobox om direct te wisselen.
- `[bso_survival_part_score]`
	- Attributen: `event_id`, `part_id`, `title`
	- Zonder `part_id` wordt automatisch het eerste gekoppelde onderdeel van het event gekozen; bovenin staat een onderdeel-combobox om direct te wisselen.
- `[bso_survival_timeslot_board]`
	- Attributen: `event_id`, `part_id`, `title`

Belangrijk voor dashboardpagina's:

- Gebruik altijd `[bso_survival_dashboard]` (niet `[bso_team_dashboard]`).
- Geef bij voorkeur altijd expliciet `event_id` mee, bijvoorbeeld: `[bso_survival_dashboard event_id="7"]`.
- Zonder `event_id` kiest de shortcode automatisch het eerstvolgende actieve event vanaf vandaag.
- Bovenin het dashboard staat een event-combobox met maximaal 5 actieve events vanaf vandaag naar de toekomst.

Voorbeeld:

```text
[bso_survival_event_overview title="Gecombineerd Overzicht Event 2" event_id="2" compact="yes"]
[bso_survival_team_score title="Tussentijdse teamscore Team001" event_id="2" team_id="14"]
[bso_survival_part_score title="Tussentijdse onderdeelscore Kano Bungee" event_id="2" part_id="8"]
[bso_survival_timeslot_board title="Tijdslot overzicht Event 2" event_id="2" part_id="8"]
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
	},
	"widths": {
		"main": {
			"team_ranking": "3/4"
		},
		"operations": {
			"message_widget": "1"
		}
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
4. Klik op een score-rij om rechts het flip-over paneel `Bewerken score, score ID #...` te openen.
5. Wijzig `Ruwe score`, zet optioneel `Bonus punten` (numeriek, >= 0) en zet indien nodig `Joker ingezet (score telt dubbel)` aan of uit.
6. Klik `Opslaan`, of klik `Annuleren`.
7. Joker is technisch afgedwongen als eenmalig per team per event; dubbele inzet wordt geblokkeerd met foutmelding.
8. Gebruik `Nieuwe score` naast `Laden` als handmatige fallback voor uitzonderingen.
9. Een extra score op hetzelfde onderdeel voor hetzelfde team is alleen toegestaan via een andere assignment/tijdslot en zolang het totaal aantal scores van alle teams gelijk blijft; anders wordt opslaan geannuleerd.
10. Voor simulaties zonder handmatige invoer: gebruik het runbook [docs/Runbook_Beheer_Demo_Simulatie.md](docs/Runbook_Beheer_Demo_Simulatie.md).
11. De scoregrid bevat nu een kolom `Tijdsrange` (sorteerbaar) zodat assignment-context direct zichtbaar is.
12. Rechtenafhankelijke UI: gebruikers met alleen scorebeheer zien wel invoer/bewerken, maar geen `Initialiseer scores` (alleen volledige beheerrechten).
13. In de edit flip-over kan `Tijdsrange` nu actief worden gewijzigd via een selectieveld; hiermee wissel je eenvoudig van tijdslot binnen hetzelfde team + onderdeel.

## Admin Quickstart (toegang en rollen)

1. Open `Survival -> Toegang`.
2. Kies per WordPress gebruiker een override-profiel.
3. Profielen:
	- `Overnemen van WordPress rol`: alleen standaard WP rechten gelden.
	- `Survival eigenaar`: volledige pluginrechten (settings + toegang + score + meldingen).
	- `Survival coordinator`: settings + score + meldingen.
	- `Alleen scorebeheer`: alleen scorebeheerpagina en score-API acties.
	- `Alleen meldingen`: alleen dashboard-meldingenbeheer.
	- `Geen Survival toegang`: verwijdert pluginrechten voor deze gebruiker.
4. Klik `Toegang opslaan`.
5. Laat de gebruiker opnieuw inloggen zodat het menu met nieuwe rechten zichtbaar wordt.

## Frontend scorelogica (team/onderdeel)

- Onderdeelscore (`[bso_survival_part_score]`) toont alle teams voor het geselecteerde onderdeel.
- Onderdeelscore bevat een extra kolom `Tijdsrange` (sorteerbaar) in het grid.
- Tijdslot-overzicht (`[bso_survival_timeslot_board]`) toont per tijdslot welke teams tegenover elkaar staan, met een groene of grijze status-led per team op basis van score-aanwezigheid.
- Voor gebruikers met scorebeheerrechten (`manage_survival_scores`) zijn score-rijen in onderdeelscore aanklikbaar en opent rechts een bewerk flip-over.
- Zonder scorebeheerrechten toont onderdeelscore een duidelijke alleen-lezen UI en is bewerken uitgeschakeld.
- Teamscore hanteert nu hetzelfde gridgedrag als onderdeelscore: voor scorebeheerders aanklikbare rijen met rechter bewerkpaneel; voor read-only gebruikers alleen weergave.
- Posities worden per onderdeel opnieuw berekend op basis van ruwe score en onderdeelregel (`lower_raw_wins` / `higher_raw_wins`, of `time` als lagere score wint).
- Bij gelijke ruwe score geldt bonus als tie-break (meer bonus = hogere positie); pas bij gelijke bonus volgt alfabetische fallback op teamnaam.
- Per team wordt een tussentijdse waarde berekend met:
	- `tussentijdse_score = positie * 10 * joker_factor`
	- `joker_factor = 1` zonder joker, `2` met joker.
- Positie in het grid blijft de originele rangorde (1, 2, 3, ...).
- Voor de tussentijdse score wordt intern een omgekeerde weging gebruikt (bij 7 teams: beste rij telt als 7, laagste rij als 1).
- Teamscore (`[bso_survival_team_score]`) toont voor het geselecteerde team per onderdeel:
	- tijdsrange
	- ruwe score
	- bonuspunten
	- berekende positie binnen dat onderdeel
	- tussentijdse score
- De tussentijdse eindscore van het team is de som van alle tussentijdse onderdelencores.

## CLI Quickstart (dagafsluiting)

- `wp bso-survival lifecycle --phase=closeout --event_id=14 --changed_by=wedstrijdleiding --certificates='[{"team_id":5,"file_path":"/tmp/team-5.pdf"}]'`
- `wp bso-survival lifecycle --phase=publish --event_id=14 --changed_by=wedstrijdleiding --publication='{"headline":"Uitslag gepubliceerd","standings":[{"rank":1,"team_id":11,"team_name":"Team Rood","points":98.5}],"recipients":["coach@example.test"]}'`

## CLI Quickstart (demo simulatie)

- Alle tijdsloten van een event seeden (standaardgedrag als `--slot` ontbreekt):
	- `WORDPRESS_DB_HOST=wordpress-db WORDPRESS_DB_NAME=wordpress WORDPRESS_DB_USER=wordpress WORDPRESS_DB_PASSWORD='DitIsNiet4Jou!' wp --path=/var/www/html --allow-root bso-survival seed-demo-scores --event-id=7`
- Alleen specifieke tijdsloten seeden:
	- `WORDPRESS_DB_HOST=wordpress-db WORDPRESS_DB_NAME=wordpress WORDPRESS_DB_USER=wordpress WORDPRESS_DB_PASSWORD='DitIsNiet4Jou!' wp --path=/var/www/html --allow-root bso-survival seed-demo-scores --slot=1,6,9,12 --event-id=7`
- Idempotent gedrag:
	- de command werkt update-only op bestaande score-records;
	- er worden geen nieuwe score-entries toegevoegd door demo-seeding.

Volledige beheerstappen en checks staan in [docs/Runbook_Beheer_Demo_Simulatie.md](docs/Runbook_Beheer_Demo_Simulatie.md).

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

### 0.5.12 - Demo score-seeding voor simulatie-tests

- Nieuwe CLI command `wp bso-survival seed-demo-scores` toegevoegd voor beheersimulaties.
- Demo-score dataset dekt alle tijdsloten van het event; zonder `--slot` worden alle tijdsloten geupdate.
- Seeder is idempotent gemaakt: update-only op bestaande score-records, zonder nieuwe score-entry inserts.

### 0.5.13 - Bonusveld en tie-resolutie

- Score Invoer ondersteunt nu een numeriek veld `bonus_points` in create/edit flip-over en in de scoregrid (inclusief sortering).
- Onderdeelscore en teamscore shortcodes tonen nu bonuspunten en ondersteunen sortering op bonus.
- Bij gelijke `raw_value` bepaalt bonuspunten de onderlinge rangorde (meer bonus wint tie).

### 0.5.14 - Toegang en rollen

- Nieuwe adminpagina `Survival -> Toegang` toegevoegd met per-gebruiker override-profielen.
- Plugin gebruikt nu eigen capabilities voor settings, toegang, scorebeheer en meldingenbeheer.
- Settings-gebonden adminpagina's en relevante REST beheeracties respecteren toegewezen survival-caps.

### 0.5.15 - Timeslotkolommen en rechtenafhankelijke score-UI

- `Survival -> Score Invoer` toont nu een sorteerbare `Tijdsrange` kolom in de scoregrid en in assignmentcontext.
- `Survival -> Score Invoer` UI differentieert rechten: scorebeheer vs volledige beheerrechten (initialisatieknop alleen voor volledige rechten).
- `Survival -> Score Invoer` edit flip-over ondersteunt nu tijdslotwissel via selecteerbare tijdsrange.
- `[bso_survival_part_score]` toont nu een `Tijdsrange` kolom, inclusief sorteermogelijkheid en editorcontext met range-label.
- `[bso_survival_team_score]` toont nu ook de `Tijdsrange` per rij.

### 0.5.16 - Score-shortcode pariteit en tijdslotpairing

- `[bso_survival_part_score]` gebruikt nu assignment/tijdslot-niveau rows, zodat per tijdslot beide teams zichtbaar blijven.
- `[bso_survival_part_score]` sorteert standaard op tijdsrange en toont visuele scheiding tussen tijdslotblokken.
- `[bso_survival_team_score]` gebruikt hetzelfde rechtenafhankelijke gridgedrag als onderdeelscore (click-to-edit voor scorebeheer).
- `[bso_survival_team_score]` forceert voor tijdsrange altijd oplopende sortering (vroeg naar laat), ook bij oude URL-sortparams.

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
