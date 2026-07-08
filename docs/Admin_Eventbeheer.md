# Admin Eventbeheer

Laatste update: 8 juli 2026.

## Doel

Beheerders kunnen via Admin events aanmaken, bestaande parts koppelen en events veilig verwijderen uit de actieve administratie.

## Beschikbare adminflow

Menu:
- Survival -> Events

Functionaliteit:
- Nieuw event aanmaken (naam, datum, max teams)
- Bestaande parts koppelen/ontkoppelen aan geselecteerd event
- Event verwijderen uit actieve administratie zonder part-verlies

## Belangrijke regels

- Gesloten/gepubliceerde/verwijderde events zijn immutable voor inhoudelijke mutaties (zoals part-koppelingen).
- Verwijderen van een event verwijdert geen part-definities.
- Parts worden bij verwijderen losgekoppeld (`event_id` naar `NULL`) en blijven herbruikbaar.
- Voor gesloten/gepubliceerde events is een samenvatting/publicatie vereist voordat verwijderen vanuit admin is toegestaan.

## Verwacht gedrag bij verwijderen

- Eventstatus wordt `verwijderd`.
- Event verdwijnt uit standaard eventselecties in admin/frontend flows.
- Part-records blijven bestaan en zijn opnieuw koppelbaar aan andere events.

## Automatische tests

Specifieke tests draaien:

```bash
./scripts/run-admin-event-tests.sh
```

Volledige regressiesuite:

```bash
vendor/bin/phpunit
```
