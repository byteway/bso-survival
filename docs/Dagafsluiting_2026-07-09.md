# Dagafsluiting 9 juli 2026

Status: Afgerond

## Samenvatting van vandaag

Vandaag is de adminflow rondom Events en onderdeelinstellingen verder afgerond en gestabiliseerd:

- In Admin -> Survival -> Events is het onderdelen-grid sorteerbaar gemaakt op:
  - Koppelen
  - Part
  - Huidig event
- In hetzelfde grid is de kolom Part klikbaar gemaakt voor gekoppelde onderdelen.
- Klik op een gekoppelde part opent een derde rechter flip-over paneel met onderdeelinstellingen.
- Paneel 3 bevat Opslaan en Annuleren en gebruikt dezelfde rule-velden als obstacle-specific rules.
- De paneeltitel toont nu de naam van het geladen event.
- Boven de eigenschappen wordt nu expliciet getoond:
  - Part nr
  - Onderdeelnaam
- Extra server-side validatie toegevoegd:
  - Instellingen opslaan vanuit paneel 3 mag alleen als de koppeling part-event al persistent is opgeslagen.
  - Scenario "wel aangevinkt maar nog niet opgeslagen" wordt nu veilig geblokkeerd met duidelijke melding.
- Layoutfix op Admin -> Survival -> obstacle-specific rules:
  - Eventselectie + Laden staan nu bovenaan.
  - Samenvatting volgt daaronder, zodat bediening niet naar beneden wordt gedrukt.

Aanvullend is de Score Invoer-flow gemoderniseerd:

- Admin -> Survival -> Score Invoer toont nu een klikbare scorelijst per event.
- Klik op een score opent een rechter flip-over paneel met context (score-id, team, onderdeel) en knoppen `Opslaan` / `Annuleren`.
- Naast `Laden` staat nu een knop `Nieuwe score`.
- Naast `Laden` staat nu ook `Initialiseer scores`:
  - maakt alle ontbrekende score-records aan voor assignments van het gekozen event;
  - slaat bestaande records over;
  - gebruikt de bestaande assignment-planning als bron (planningsconstraints blijven daarmee intact).
- Databasefout opgelost in score-overzicht: selectie gebruikt `entered_by_role` i.p.v. niet-bestaande kolom `changed_by`.
- Joker-inzet is nu functioneel in Score Invoer:
  - checkbox in create/bewerkpaneel;
  - eenmalig per team per event afgedwongen;
  - opslag in `joker_usages` met validatiegebruiker en timestamp;
  - bij joker verdubbelt `normalized_points`.
- Documentatie van score-eigenschappen is aangescherpt in `docs/Admin_Eventbeheer.md`.

## Gewijzigde bestanden

- src/Admin/EventAdminPage.php
- src/Admin/PartRuleAdminPage.php
- src/Core/Plugin.php
- docs/Admin_Eventbeheer.md
- src/Admin/ScoreEntryAdminPage.php
- src/Service/AdminScoreService.php
- src/Database/Repository/ScoreEntryRepository.php
- src/Database/Repository/ScoreEntryRepositoryInterface.php
- README.md

## Kwaliteitscontrole

Gerichte regressietests uitgevoerd:

- php vendor/bin/phpunit --filter 'EventAdminServiceTest|PartAdminServiceTest|PartRuleConfiguratorServiceTest' tests/Service
- Resultaat: OK (21 tests, 58 assertions)

## Git status

- Commit: be70a23
- Branch: main
- Push: succesvol naar origin/main

## Open voor volgende sessie

- Verdere afbouw van admin part page-taken uit de backlog.
- Extra testcases toevoegen voor paneel-3 validatiepaden (optioneel als aparte testscope).

## Opmerking

Deze dagafsluiting is bedoeld als korte overdracht voor de volgende werksessie.
