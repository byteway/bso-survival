# F6-03 Handmatige Smoke Checks

Laatste update: 8 juli 2026.

## Doel

Snelle controle op randmomenten voor message-zichtbaarheid met `visible_from` en `visible_until`.

## Script

- [tests/manual/f6-03-smoke.sh](../tests/manual/f6-03-smoke.sh)

## Voorwaarden

- WordPress + plugin draaien in de huidige omgeving
- WP-CLI beschikbaar (`wp`)
- Geldig `event_id` met rechten om dashboard messages te muteren

## Uitvoeren

```bash
./tests/manual/f6-03-smoke.sh <event_id>
```

of

```bash
BSO_EVENT_ID=<event_id> ./tests/manual/f6-03-smoke.sh
```

## Wat wordt gecontroleerd

1. `visible_from` in de toekomst: message mag nog niet zichtbaar zijn.
2. `visible_until` in het verleden: message mag niet zichtbaar zijn.
3. Huidig venster (`visible_from=nu`, `visible_until=nu+3m`): message moet zichtbaar zijn.
4. Ongeldig venster (`visible_until <= visible_from`): create moet worden geweigerd.

## Verwachte uitkomst

- Per check een `PASS` regel
- Eindregel `F6-03 SMOKE RESULT: PASS`
- Exit code `0`

Bij fout:

- Eén of meer `FAIL` regels
- Eindregel `F6-03 SMOKE RESULT: FAIL`
- Exit code `1`

## Cleanup

Het script verwijdert aangemaakte smoke-test messages automatisch na de run.
