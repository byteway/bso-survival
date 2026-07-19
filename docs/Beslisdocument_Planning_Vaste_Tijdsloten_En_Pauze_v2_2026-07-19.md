# Beslisdocument v2 - Planning met vaste tijdsloten en pauze

Status: akkoord
Datum: 2026-07-19
Scope: BSO Survival planning en score-assignment generatie
Vervangt: Beslisdocument_Planning_Vaste_Tijdsloten_En_Pauze_2026-07-19.md

## 1. Doel

Dit document legt de definitieve businessregels vast voor planning en score-assignment generatie, inclusief:

1. vaste tijdslotlijst;
2. vast pauzeslot 12:05 - 12:35;
3. formele bronvelden voor onderdelen- en teamcount;
4. expliciet overflow-beleid.

## 2. Definitieve besluiten

1. Onderdelen-count wordt bepaald op basis van de actuele count van onderdelen die aan het event gekoppeld zijn.
2. Teams-count wordt bepaald op basis van teams die op tijd zijn ingeschreven binnen het registratievenster.
3. Het registratiedeadline-veld is `registration_windows.closes_at` van het laatst opgeslagen venster voor het event.
4. Alleen teamstatussen `ingeschreven` en `bevestigd` tellen mee voor planningsdeelname.
5. De planning gebruikt een vaste, niet-dynamische tijdslotlijst.
6. Het slot 12:05 - 12:35 is altijd pauze en bevat nooit assignments of score-initialisatie.
7. Pairing gebruikt de Circle Method (Bergertabel) met Bye-ondersteuning bij oneven teams.
8. Overflow-beleid is verplicht handmatige herplanning (geen automatische truncatie of extra slots).

## 3. Formele definities

### 3.1 Onderdelen-count

- Bron: koppelingen event -> onderdeel.
- Filter: onderdelen met status ongelijk aan `verwijderd`.
- Formule: onderdelen_count = count(gekoppelde, actieve onderdelen).

### 3.2 Teams-count (op tijd ingeschreven)

- Bron 1: `teams`.
- Bron 2: `registration_windows`.
- Deadline: `closes_at` van het laatst opgeslagen registratievenster van het event.
- Inclusievoorwaarden:
  - team.event_id = geselecteerd event;
  - team.status in (`ingeschreven`, `bevestigd`);
  - team.created_at <= deadline.
- Formule: teams_count = count(teams die aan alle inclusievoorwaarden voldoen).

### 3.3 Vaste tijdslotlijst

| Slot | Start | Einde | Type |
|---|---|---|---|
| 1 | 09:00 | 09:30 | wedstrijd |
| 2 | 09:35 | 10:05 | wedstrijd |
| 3 | 10:10 | 10:40 | wedstrijd |
| 4 | 10:45 | 11:15 | wedstrijd |
| 5 | 11:20 | 11:50 | wedstrijd |
| 6 | 12:05 | 12:35 | pauze |
| 7 | 12:40 | 13:10 | wedstrijd |
| 8 | 13:15 | 13:45 | wedstrijd |
| 9 | 13:50 | 14:20 | wedstrijd |
| 10 | 14:25 | 14:55 | wedstrijd |
| 11 | 15:00 | 15:30 | wedstrijd |
| 12 | 15:35 | 16:05 | wedstrijd |
| 13 | 16:10 | 16:40 | wedstrijd |
| 14 | 16:45 | 17:15 | wedstrijd |

Regel: slot 6 is hard gereserveerd als pauze en wordt technisch uitgesloten van assignment-generatie.

## 4. Overflow-beleid (definitief)

Wanneer het aantal te plannen wedstrijden groter is dan de capaciteit van beschikbare wedstrijdsloten:

1. planner stopt met een functionele validatiefout;
2. er worden geen gedeeltelijke nieuwe assignments weggeschreven;
3. beheerder moet handmatig herplannen (teams/onderdelen/venster aanpassen) en opnieuw genereren.

Doel: consistentie en voorspelbaarheid boven impliciete dataverlies- of truncatiekeuzes.

## 5. Validatievoorwaarden voor start planning

1. Event bestaat en is niet immutable (gesloten/gepubliceerd/verwijderd).
2. Er is minimaal 1 gekoppeld actief onderdeel.
3. Er zijn minimaal 2 teams die voldoen aan de tijdige-inschrijving-regel.
4. Er is een registratievenster aanwezig met geldige `closes_at`.
5. Capaciteitscheck (wedstrijden vs beschikbare wedstrijdsloten exclusief pauze) slaagt.

## 6. Acceptatiecriteria

1. Onderdelen-count volgt exact de actieve event-koppelingen.
2. Teams-count gebruikt deadlinefilter op `created_at <= closes_at`.
3. Alleen `ingeschreven` en `bevestigd` tellen mee voor teamdeelname.
4. Pauzeslot 12:05 - 12:35 bevat 0 assignments.
5. Score-initialisatie slaat pauzeslot impliciet over doordat daar geen assignments bestaan.
6. Overflow resulteert in blokkade met duidelijke foutmelding, zonder gedeeltelijke planning.

## 7. Implementatie-impact

Te actualiseren na akkoord:

1. Service-logica voor planning/generatie in EventAdminService.
2. Teamcount-query voor planning (nieuwe deadline- en statusfiltering).
3. Validatie en foutmeldingen in adminflow voor planning/genereren.
4. Testcases voor:
   - deadlinefilter;
   - statuswhitelist;
   - vast pauzeslot;
   - overflow-failure zonder partial writes.

## 8. Beslisnotitie

Deze v2 kiest bewust voor strikte en transparante regels:

1. geen impliciete automatische extra planning buiten vaste slotlijst;
2. geen open statusinterpretatie van teams;
3. geen planning zonder expliciet registratievenster.
