# Dagafsluiting Voorbereiding

> Statusnotitie 7 juli 2026: read-only overzichtsschermen zijn nu beschikbaar via frontend shortcodes, maar de definitieve dagafsluitflow blijft buiten scope van dit document.

Dit document bereidt de dagafsluiting voor, maar maakt die nog niet definitief.

De survivaldag is in deze fase nog actief of nog niet afgerond. Daarom bevat dit document alleen de voorbereidingsstappen, afbakeningen en checklists die nodig zijn om later een valide dagafsluiting te bouwen zonder de huidige daglogica te blokkeren.

## Doel

De dagafsluiting later mogelijk maken op basis van bestaande repositories, services en de golden dataset, zonder nu al de finale eindstand- of certificaatlogica vast te leggen.

## Scope

Wel in scope:
- voorbereiding van sluitingsvoorwaarden
- controle op volledigheid van scores
- markering van openstaande tijdsloten
- afbakening voor read-only overgang
- voorbereiding voor eindstand en certificaatverwerking

Niet in scope:
- definitieve eindstandberekening
- definitieve podiumplaatsen
- certificaatgeneratie voor productie
- read-only afdwinging van de dag
- bedankbericht of publicatieflow

## Voorbereidende stappen

### 1. Controleer openstaande resultaten

- Bepaal welke onderdelen nog geen definitieve score hebben.
- Controleer welke teams nog ontbreken in de tussenstand.
- Markeer tijdsloten die nog in verwerking zijn.

### 2. Controleer afsluitvoorwaarden

- Alle verplichte scores moeten aanwezig zijn voordat sluiting kan worden gestart.
- Jokergebruik moet geregistreerd zijn voordat finale berekening begint.
- Eventstatus mag nog niet op read-only worden gezet zolang de dag niet voorbij is.

### 3. Bereid publicatie voor

- Verzamel gegevens voor eindstand, top 3 en certificaatinput.
- Zorg dat de relevante data via repositories beschikbaar is.
- Laat de uiteindelijke publicatie nog buiten scope tot de dag daadwerkelijk is afgerond.

### 4. Bereid logging voor

- Leg vast welke afsluitactie later gelogd moet worden.
- Voorzie een audit-entry voor latere sluiting.
- Houd de daadwerkelijke afsluitactie voorlopig uit de codeflow.

## Prerequisites voor een latere definitieve dagafsluiting

- repository-laag voor events, teams en onderdelen is beschikbaar
- service-laag voor read-only dataverwerking is beschikbaar
- golden dataset is aanwezig voor regressietesten
- statusovergangen zijn expliciet en testbaar
- scoredata en jokerdata kunnen later worden samengevoegd

## Acceptatie van deze voorbereidende fase

- Er bestaat een duidelijke scheiding tussen voorbereiding en definitieve afsluiting.
- De dag kan nog actief blijven terwijl afsluitvoorwaarden worden gecontroleerd.
- De uiteindelijke sluiting wordt bewust pas in een volgende stap uitgewerkt.

## Volgende stap

Wanneer de dag daadwerkelijk voorbij is, kan dit document worden uitgebreid met de definitieve eindstand-, certificaat- en read-only flow.
