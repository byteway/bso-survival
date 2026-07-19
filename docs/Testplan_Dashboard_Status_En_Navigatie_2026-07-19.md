# Testplan Dashboard Status en Navigatie (2026-07-19)

## Doel
Valideren dat het Survival Dashboard:
- eventstatus en registratiestatus als SVG toont;
- status-tekst alleen op hover/focus toont;
- kernwaarden echte eventdata gebruiken;
- klikbare onderdelen en teams correct doorlinken naar help en teamscore.

## Scope
- Frontend dashboard shortcode: `[bso_survival_dashboard]`
- Frontend onderdelen shortcode: `[bso_survival_parts]`
- Frontend teamscore shortcode: `[bso_survival_team_score]`
- Event met minimaal 2 onderdelen en 2 teams

## Preconditions
1. Een event bestaat met status `concept`, `gepland`, `actief` of `afgesloten`.
2. Voor minimaal 1 onderdeel is een helptekst opgeslagen.
3. Dashboardpagina bevat bij voorkeur:
   - `[bso_survival_dashboard event_id="<event_id>"]`
   - `[bso_survival_parts event_id="<event_id>"]`
   - `[bso_survival_team_score event_id="<event_id>"]`
4. Event heeft `max_teams` in `meta_data` (bijv. 22).

## Testgevallen

### TC-01 Eventstatus SVG en hovertekst
1. Open dashboardpagina.
2. Zoek KPI-kaart `Status`.
3. Controleer dat een SVG zichtbaar is (geen platte status-tekst als hoofdwaarde).
4. Hover met muis op het status-icoon.
5. Zet keyboard focus op het status-icoon (Tab).

Verwacht:
- SVG wordt geladen uit `assets/images/event-status/`.
- Statuslabel verschijnt alleen bij hover/focus als tooltip.
- Tooltip verdwijnt buiten hover/focus.

### TC-02 Registratiestatus SVG en hovertekst
1. Open dashboardpagina.
2. Zoek KPI-kaart `Inschrijving`.
3. Controleer dat status-SVG zichtbaar is.
4. Hover/focus op icoon.

Verwacht:
- SVG komt uit `assets/images/registration-status/`.
- Tooltip toont `Inschrijving open` of `Inschrijving gesloten`.
- Teller `x / max_teams` blijft zichtbaar buiten hover.

### TC-03 Kernwaarden tonen echte waarden
1. Noteer voor testevent:
   - aantal gekoppelde onderdelen;
   - aantal teams;
   - aantal geregistreerde teams;
   - max teams.
2. Vergelijk met dashboard KPI's.

Verwacht:
- `Onderdelen` = echt aantal gekoppelde onderdelen.
- `Teams` = echt aantal teams voor event.
- `Inschrijving` teller = geregistreerde teams / max teams.
- `Klaar voor planning` is `Ja` alleen als onderdelen > 0 en teams > 0.

### TC-04 Widget Onderdelen is klikbaar
1. Open `<details>` blok `Onderdelen`.
2. Klik op een onderdeelnaam.

Verwacht:
- URL bevat `event_id` en `part_id`.
- Pagina springt naar onderdelen-sectie (`#bso-survival-parts-event-<event_id>`).
- Juiste helptekst van gekozen onderdeel wordt getoond.

### TC-05 Widget Teams is klikbaar
1. Open `<details>` blok `Teams`.
2. Klik op een teamnaam.

Verwacht:
- URL bevat `event_id` en `team_id`.
- Pagina springt naar teamscore-sectie (`#bso-survival-team-score-event-<event_id>`).
- Team-scoreformulier toont geselecteerd team.

### TC-06 Registratie vol / gesloten gedrag
1. Zet event op read-only status (`afgesloten` of `gepubliceerd`) of vul teams tot max.
2. Herlaad dashboard.

Verwacht:
- Registratiestatus wordt `gesloten` met bijbehorende SVG.
- Hovertekst reflecteert gesloten status.
- Read-only notice blijft zichtbaar indien van toepassing.

### TC-07 URL override filters (optioneel)
1. Voeg filter toe voor `bso_survival_dashboard_parts_help_url` naar aparte pagina met parts-shortcode.
2. Voeg filter toe voor `bso_survival_dashboard_team_score_url` naar aparte pagina met team-score-shortcode.
3. Klik opnieuw op onderdeel/team in dashboard.

Verwacht:
- Links gebruiken de gefilterde basis-URL.
- Query args `event_id`, `part_id` of `team_id` blijven correct.

### TC-08 Admin ingestelde doelpagina per event
1. Open admin `Survival -> Dashboard Widgets`.
2. Kies testevent.
3. Stel `Onderdelenlijst pagina` in op pagina A (met shortcode `[bso_survival_parts]`).
4. Stel `Teamscore pagina` in op pagina B (met shortcode `[bso_survival_team_score]`).
5. Sla op en herlaad dashboard.
6. Klik op een onderdeelnaam en daarna op een teamnaam.

Verwacht:
- Onderdeel-link opent pagina A met `event_id` + `part_id` en juiste anchor.
- Team-link opent pagina B met `event_id` + `team_id` en juiste anchor.
- Als beide velden op `Huidige dashboardpagina gebruiken` staan, blijft fallback op huidige pagina werken.

## Acceptatiecriteria
- Alle testgevallen slagen zonder PHP-fouten.
- Geen hardcoded placeholder-waarden zichtbaar in dashboard-kernwaarden.
- Navigatie van dashboard naar help/team-score werkt met juiste context.

## Aanvullende UI-checks scoreformulieren (timeslot consistentie)

### TC-09 Team Score timeslot-kaarten
1. Open pagina met `[bso_survival_team_score]` voor een event met meerdere tijdsloten.
2. Controleer per scoreblok:
   - rij 1 toont tijdsrange;
   - rij 2 toont onderdeelnaam;
   - rij 3 toont scorewaarden (ruwe score, bonus, joker, positie, tussentijdse score).
3. Controleer dat lange labels/waarden niet over elkaar heen vallen.

Verwacht:
- Elke tijdsrange staat in een eigen duidelijk kader.
- Waarden blijven leesbaar op desktop en mobiel.

### TC-10 Part Score timeslot-kaarten
1. Open pagina met `[bso_survival_part_score]` voor een event met meerdere tijdsloten.
2. Controleer per scoreblok:
   - rij 1 toont tijdsrange;
   - rij 2 toont teamnaam;
   - rij 3 toont scorewaarden (ruwe score, bonus, joker, positie, tussentijdse score).
3. Controleer dat sorteerlinks blijven werken.

Verwacht:
- Zelfde visuele structuur als Team Score (consistentie).
- Geen schuine/afgebroken onleesbare kolomtitels meer.
