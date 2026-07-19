# Dagafsluiting 19 juli 2026

Status: Afgerond

## Samenvatting van vandaag

Vandaag is de dashboard-widgetlaag functioneel uitgebreid, getest en gedeployed.

Belangrijkste resultaten:

- Planning v2 (vaste tijdsloten + pauzeregel) aanvullend getest met extra gerichte scenario's.
- Contactzoeker volledig functioneel gemaakt:
  - live zoeken op team/contact/e-mail/telefoon/status;
  - zonder zoekterm geen resultaten;
  - clear-knop (`x`) naast zoekveld;
  - telefoonkoppeling via WhatsApp (`wa.me`).
- Fallback-scoreinvoer documentatie gecorrigeerd naar daadwerkelijke implementatie (leiding-only noodkaart).
- Inschrijfcapaciteit uitgewerkt als echte operationele widget:
  - status: open, beperkt, vol, gesloten;
  - verhouding `ingeschreven / max`;
  - resterende plaatsen;
  - bezettingsindicator/progress.
- Frontend regressie opgelost waardoor geselecteerde widgets in sommige configuraties niet zichtbaar waren.
- Nieuwe dashboardnavigatie toegevoegd:
  - naast `Onderdelenlijst pagina` en `Teamscore pagina` nu ook `Inschrijfpagina` per event.
- Inschrijfcapaciteit gekoppeld aan ingestelde inschrijfpagina:
  - bij beschikbare capaciteit toont widget een directe `Inschrijven`-knop naar het formulier.

## Code-opgeleverd

- Widget gedrag en rendering:
  - `src/Widgets/ContactFinderWidget.php`
  - `src/Widgets/RegistrationCapacityWidget.php`
  - `src/Service/DashboardWidgetRegistry.php`
  - `src/Frontend/DashboardController.php`
- Dashboard widgetbeheer / configuratie:
  - `src/Admin/DashboardWidgetAdminPage.php`
  - `src/Service/DashboardWidgetLayoutService.php`
- Frontend styling / gedrag:
  - `assets/css/bso-survival-dashboard-widgets.css`
  - `assets/js/bso-survival-dashboard-widgets.js`

## Tests toegevoegd / bijgewerkt

- `tests/Service/EventAdminServiceTest.php`
  - extra planning-v2 tests: deadlinefilter, status-whitelist, overflow zonder partial writes.
- `tests/Service/ContactFinderWidgetTest.php`
  - zoekgedrag, CTA's en lege states.
- `tests/Service/RegistrationCapacityWidgetTest.php`
  - open/beperkt/vol/gesloten + CTA wel/niet tonen.
- `tests/Service/DashboardWidgetRegistryTest.php`
  - zichtbaarheid publieke widgets en capability-randgevallen.
- `tests/Service/DashboardControllerTest.php`
  - dashboard asset-enqueue en renderstabiliteit.
- `tests/Service/DashboardWidgetLayoutServiceTest.php`
  - navigatie-uitbreiding met `registration_page_id`.

## Documentatie bijgewerkt

- `README.md`
- `docs/Functional_Design_v2.md`
- `docs/Implementation_Roadmap_v2.md`
- `docs/Admin_Eventbeheer.md`
- `docs/Beslisdocument_Planning_Vaste_Tijdsloten_En_Pauze_v2_2026-07-19.md`

## Validatie

Uitgevoerde checks en resultaten:

- Meerdere `get_errors`-runs op aangepaste bestanden: geen syntaxproblemen.
- Gerichte PHPUnit-runs op gewijzigde domeinen:
  - `tests/Service/EventAdminServiceTest.php`: groen.
  - `tests/Service/ContactFinderWidgetTest.php`: groen.
  - `tests/Service/RegistrationCapacityWidgetTest.php`: groen.
  - `tests/Service/DashboardWidgetLayoutServiceTest.php`: groen.
  - `tests/Service/DashboardControllerTest.php`: groen.
  - `tests/Service/DashboardWidgetRegistryTest.php`: groen.

## Deploy

Deploy meerdere keren succesvol uitgevoerd via:

```bash
cd /config/workspace/projects/deploy-bso-survival
./quickstart-bso-survival.sh
```

Resultaat per run:

- Plugin gedeactiveerd -> rsync deploy -> plugin geactiveerd.
- Cache en transients opgeschoond.
- Pluginstatus na controle: actief.

## Commits van vandaag (samenvatting)

- `cff2252` Add targeted planning v2 tests for deadline status and overflow
- `716bb44` Implement Contactzoeker widget with live search and tests
- `6b8f306` Refine Contactzoeker behavior and update documentation
- `b0a0d92` Clarify fallback score widget documentation
- `2614fa9` Document registration capacity widget
- `35de3ef` Fix dashboard widget visibility and registration capacity rendering
- `2f40ef0` Add registration page CTA for capacity widget

## Open punten voor volgende sessie

- Optioneel: extra UI-highlight op dashboard wanneer inschrijving bijna vol is (bijv. <= 2 plaatsen).
- Optioneel: korte beheer-tooltip in Dashboard Widgets bij `Inschrijfpagina` om afhankelijkheid van shortcodepagina te verduidelijken.
- Optioneel: extra e2e smoke-script op runtime voor volledige klikflow `Inschrijfcapaciteit -> Inschrijfformulier`.
