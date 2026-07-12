# F6-06 Handmatig Testscript Eindscore Bevestiging

## Doel
Valideren dat de eindscore-flow werkt zoals gevraagd:
- onderdeel pas bevestigen na laatste score
- bevestigen blokkeren bij ties
- na bevestigen geen scorewijzigingen meer voor scheidsrechters
- leiding kan wel corrigeren
- na bevestiging van alle onderdelen automatische eindstand en samenvattingsmail

## Testduur
Ongeveer 10 tot 20 minuten.

## Randvoorwaarden
- Plugin bso-survival staat actief in WordPress.
- Event met minimaal 2 onderdelen en minimaal 2 teams.
- Er zijn assignments voor alle combinaties die je wilt testen.
- Je hebt 2 accounts:
  - Scheidsrechteraccount met scorebeheer
  - Leidingaccount met instellingenbeheer
- Mailuitvoer kan gecontroleerd worden via outbox of mail logging.

## Test 1 Niet bevestigbaar voor laatste score
1. Open het scoreformulier als scheidsrechter.
2. Kies een onderdeel waar nog niet alle teams een score hebben.
3. Controleer de melding bij onderdeel bevestigen.
Verwacht:
- Bevestigen is nog niet mogelijk.
- Melding geeft aan dat niet alle teams een score hebben.

## Test 2 Tie blokkeert bevestigen
1. Voer voor twee teams exact gelijke score in op hetzelfde onderdeel.
2. Controleer status en klik op onderdeel bevestigen.
Verwacht:
- Bevestigen wordt geblokkeerd.
- Melding geeft aan dat ties eerst opgelost moeten worden.

## Test 3 Tie oplossen en onderdeel bevestigen
1. Pas een van de gelijke scores aan zodat er geen tie meer is.
2. Klik onderdeel bevestigen en bevestig de waarschuwing.
Verwacht:
- Onderdeel wordt bevestigd.
- Status toont dat onderdeel bevestigd is.
- Scheidsrechter krijgt melding dat wijzigingen nu geblokkeerd zijn.

## Test 4 Lockgedrag na bevestiging
1. Probeer als scheidsrechter een score op dit bevestigde onderdeel te wijzigen.
Verwacht:
- Wijziging wordt geweigerd met lockmelding.

2. Probeer exact dezelfde wijziging als leidingaccount.
Verwacht:
- Wijziging wordt toegestaan.

## Test 5 Auto-finalisatie na alle onderdelen bevestigd
1. Herhaal bevestigen voor alle overige onderdelen, zonder ties.
2. Bevestig het laatste openstaande onderdeel.
Verwacht:
- Finalisatie wordt automatisch getriggerd.
- Eindstand wordt berekend op totaalpunten per team.
- Winnaar is team met hoogste totaal.
- Samenvattingsmail wordt aangemaakt en verzonden naar betrokkenen.

## Test 6 E-mailinhoud
1. Open de verzonden publicatie/e-mail.
2. Controleer inhoud.
Verwacht:
- Onderwerp en headline aanwezig.
- Top 3 aanwezig.
- Volledige eindstand aanwezig.
- Dankbericht aanwezig met uitnodigende afsluiting.

## Korte acceptatiecheck
Alle onderstaande punten moeten groen zijn:
- Niet bevestigbaar zolang onderdeel niet compleet is.
- Niet bevestigbaar bij ties.
- Bevestigen werkt zodra compleet en tie-vrij.
- Scheidsrechter lock actief na bevestiging.
- Leiding override werkt.
- Laatste bevestiging triggert eindstand en mail.

## Resultaatregistratie
- Tester:
- Datum:
- Event:
- Uitkomst: Go of No-Go
- Bevindingen:
