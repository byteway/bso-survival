# Beslisdocument - Planning met vaste tijdsloten en pauze

Status: concept ter akkoord
Datum: 2026-07-19
Scope: BSO Survival planning en score-assignment generatie

## 1. Doel

Dit document legt de bindende beslissingen vast voor de planningsberekening van survivalwedstrijden (timeslots, onderdelen, teams, assignments en initiële score-records).

## 2. Besluiten (bindend)

1. Aantal onderdelen wordt bepaald door de actuele count van onderdelen die aan het gekozen event gekoppeld zijn.
2. Aantal teams wordt bepaald door de actuele count van teams die zich op tijd voor het gekozen event hebben ingeschreven.
3. De planning gebruikt een vaste tijdslotlijst (geen dynamische opbouw op basis van +35 minuten).
4. Het tijdslot 12:05 - 12:35 is een vast pauzemoment.
5. In het pauzeslot worden geen assignments en geen score-records aangemaakt.
6. Team-tegen-team combinaties worden berekend met de Circle Method (Bergertabel) als basis voor eerlijke roulatie.

## 3. Formele definities

### 3.1 Onderdelen-count

- Bron: gekoppelde onderdelen van het gekozen event.
- Formule: onderdelen_count = count(koppelingen event -> onderdeel).

### 3.2 Teams-count (op tijd ingeschreven)

- Bron: teamregistraties voor het gekozen event.
- Formule: teams_count = count(teams met geldige inschrijfstatus en inschrijfmoment <= registratiedeadline event).
- Teams met status verwijderd/geannuleerd tellen niet mee.

### 3.3 Vaste tijdslotlijst

De volgende tijdsloten worden als vaste lijst gehanteerd:

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

Opmerking: slot 6 is altijd pauze en wordt nooit gevuld met wedstrijden.

## 4. Randvoorwaarden voor planning

1. Bij een oneven aantal teams wordt intern een Bye-team toegevoegd voor pairing; Bye wordt niet als echte wedstrijd opgeslagen.
2. Per wedstrijd worden twee assignments aangemaakt (een record per team) met hetzelfde timeslot en onderdeel.
3. Wedstrijden worden per ronde over onderdelen verdeeld met een verschuivende modulo-strategie om herhaling op hetzelfde onderdeel te beperken.
4. Als er meer wedstrijden zijn dan beschikbare wedstrijdsloten, worden resterende wedstrijden niet automatisch ingepland zonder expliciete overflow-regel.

## 5. Nog te bevestigen voordat implementatie start

1. Welk eventveld is leidend als registratiedeadline voor "op tijd ingeschreven".
2. Welke teamstatussen precies meetellen als geldige inschrijving.
3. Overflow-beleid bij te weinig wedstrijdsloten:
   - optie A: resterende wedstrijden laten vervallen,
   - optie B: extra eventdagdeel toevoegen,
   - optie C: handmatige herplanning verplicht.

## 6. Acceptatiecriteria

1. Planner leest onderdelen_count uit event-koppelingen en niet uit vaste configuratie.
2. Planner leest teams_count alleen uit tijdige inschrijvingen.
3. Pauzeslot 12:05 - 12:35 bevat 0 assignments.
4. Geen score-initialisatie voor assignments in pauzeslot.
5. Pairing volgt aantoonbaar de Circle Method met minimale directe herhalingen.

## 7. Effect op documentatie en implementatie

Na akkoord op dit beslisdocument moeten de volgende onderdelen in lijn worden gebracht:

1. Functioneel ontwerp (planning-hoofdstuk en constraints).
2. Admin-beheer documentatie (eventplanning en score-initialisatie).
3. EventAdminService planningslogica.
4. Testplan met scenario's voor tijdige inschrijving, vaste slots en pauzeslotcontrole.
