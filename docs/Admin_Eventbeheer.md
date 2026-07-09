# Admin Eventbeheer

Laatste update: 9 juli 2026.

## Doel

Beheerders kunnen via Admin events aanmaken en bewerken, alleen geldig koppelbare parts selecteren, onderdelen centraal beheren en events veilig verwijderen uit de actieve administratie.

## Beschikbare adminflow

Menu:
- Survival -> Onderdelen
- Survival -> Events
- Survival -> Score Invoer

Functionaliteit:
- Nieuw onderdeel aanmaken, bestaand onderdeel bewerken en veilig verwijderen/deactiveren
- Onderdelen importeren en exporteren als JSON
- Nieuw event aanmaken (naam, datum, max teams)
- Nieuw event aanmaken (naam, datum, max teams) met optionele demo-opbouw via vinkjes:
	- demo teams aanmaken met instelbaar aantal
	- alle beschikbare onderdelen koppelen
	- planning + initiële score-records genereren
- Bestaand event bewerken (naam, datum, max teams)
- Bestaande parts koppelen/ontkoppelen aan geselecteerd event met zoekfilter
- Koppelgrid in Events is sorteerbaar op `Koppelen`, `Part` en `Huidig event`
- In Events opent een klik op een gekoppelde part een rechterpaneel met onderdeelinstellingen voor het geladen event
- Event verwijderen uit actieve administratie zonder part-verlies
- Score Invoer toont een klikbare scorelijst; klik op een score opent rechts een flip-over bewerkpaneel
- Score Invoer bevat een knop `Initialiseer scores` die ontbrekende score-records voor alle assignments van het gekozen event vooraf aanmaakt
- Score Invoer ondersteunt jokerregistratie via checkbox per score; joker telt de genormaliseerde score dubbel en is maximaal 1x inzetbaar per team per event
- Event-create demo-opbouw gebruikt bij scoregeneratie een round-robin-achtige planning zodat teams zo min mogelijk direct dezelfde tegenstander treffen binnen de automatisch gegenereerde rondes.

## Belangrijke regels

- Gesloten/gepubliceerde/verwijderde events zijn immutable voor inhoudelijke mutaties (zoals part-koppelingen).
- Verwijderen van een event verwijdert geen part-definities.
- Parts worden bij verwijderen losgekoppeld (`event_id` naar `NULL`) en blijven herbruikbaar.
- Voor gesloten/gepubliceerde events is een samenvatting/publicatie vereist voordat verwijderen vanuit admin is toegestaan.
- Binnen één event mogen gekoppelde parts geen dubbele naam hebben.
- Event-bewerking toont alleen parts die geldig gekoppeld kunnen worden aan het gekozen event.
- Parts die nog aan een actief ander event hangen zijn niet selecteerbaar voor het huidige event.
- Verwijderen van een onderdeel faalt veilig zolang dat onderdeel nog aan een actief event hangt.
- Import weigert ongeldige JSON-records en dubbele partnamen.
- Opslaan van onderdeelinstellingen vanuit Events is alleen toegestaan als de part-koppeling met het event al persistent is opgeslagen.
- Score-initialisatie gebruikt expliciet de bestaande assignment-planning van het event; de eerder afgedwongen planningsregels blijven dus ongewijzigd leidend:
	- teams worden zo evenwichtig mogelijk tegen verschillende teams ingedeeld
	- elk team wordt minimaal een keer op een onderdeel ingepland
- Initialisatie maakt alleen ontbrekende score-records aan en laat bestaande entries ongemoeid.
- Een extra score op hetzelfde onderdeel voor hetzelfde team is alleen toegestaan als dit via een ander tijdslot/andere assignment loopt en het totaal aantal scores van alle teams gelijk blijft.
- Als die voorwaarde niet gehaald wordt, annuleert de plugin de actie en toont een foutmelding dat dit niet is toegestaan.
- Een joker kan slechts op één score-entry per team binnen hetzelfde event actief zijn; tweede inzet wordt server-side geweigerd.
- Bij het uitzetten van een eerder ingestelde joker op een score-entry wordt de jokerregistratie direct verwijderd.
- De create-optie `Planning + score-records genereren` werkt alleen als het event teams en gekoppelde onderdelen heeft; op een nieuw event betekent dit praktisch dat `Demo teams aanmaken` en `Alle beschikbare onderdelen koppelen` samen gebruikt moeten worden.

## Score-eigenschappen

Per score-entry worden de volgende eigenschappen vastgelegd:

- `raw_value`: ruwe ingevoerde scorewaarde
- `normalized_points`: genormaliseerde score op basis van de scoreregel van het onderdeel; bij jokerinzet verdubbeld opgeslagen
- `joker_applied`: `0/1` indicator of joker op deze score actief is
- `entered_by_role`: invoerbron/rol (bijv. `admin`, `admin_init`, `frontend_jury`)
- `entered_at`: moment van score-invoer
- `status`: huidige verwerkingsstatus van de score-entry (standaard `concept`)

Aanvullend wordt voor jokergebruik een aparte registratie bijgehouden in `joker_usages` met `event_id`, `team_id`, `score_entry_id`, `used_at` en `validated_by`.

## Verwacht gedrag bij verwijderen

- Eventstatus wordt `verwijderd`.
- Event verdwijnt uit standaard eventselecties in admin/frontend flows.
- Part-records blijven bestaan en zijn opnieuw koppelbaar aan andere events.

## Automatische tests

Specifieke tests draaien:

```bash
./scripts/run-admin-event-tests.sh
./scripts/run-admin-part-tests.sh
```

Volledige regressiesuite:

```bash
vendor/bin/phpunit
```
