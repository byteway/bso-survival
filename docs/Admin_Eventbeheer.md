# Admin Eventbeheer

Laatste update: 8 juli 2026.

## Doel

Beheerders kunnen via Admin events aanmaken en bewerken, alleen geldig koppelbare parts selecteren, onderdelen centraal beheren en events veilig verwijderen uit de actieve administratie.

## Beschikbare adminflow

Menu:
- Survival -> Onderdelen
- Survival -> Events

Functionaliteit:
- Nieuw onderdeel aanmaken, bestaand onderdeel bewerken en veilig verwijderen/deactiveren
- Onderdelen importeren en exporteren als JSON
- Nieuw event aanmaken (naam, datum, max teams)
- Bestaand event bewerken (naam, datum, max teams)
- Bestaande parts koppelen/ontkoppelen aan geselecteerd event met zoekfilter
- Event verwijderen uit actieve administratie zonder part-verlies

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
