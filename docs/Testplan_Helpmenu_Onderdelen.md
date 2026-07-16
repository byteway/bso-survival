# Testplan Helpmenu Onderdelen

## Doel
Verifieer dat helpteksten met HTML-template en afbeeldingen per onderdeel correct werken in admin en frontend, inclusief navigatie op desktop en mobiel.

## Scope
- Admin onderdelenbeheer: opslaan, valideren, preview.
- Frontend shortcode `[bso_survival_parts]`: detailweergave, afbeeldingen, bladernavigatie.
- Responsief gedrag op desktop en mobiel.

## Voorwaarden
- Plugin geactiveerd.
- Minimaal 3 onderdelen gekoppeld aan hetzelfde event.
- Pagina met shortcode `[bso_survival_parts event_id="<event_id>"]`.
- Gebruiker met `manage_survival_settings` (of equivalent via Survival toegangspagina).

## Testdata
Gebruik voor onderdeel A:
- Naam: `Kanovaren`
- Latitude: `52.1000`
- Longitude: `5.1000`
- Meta JSON: `{"difficulty":"medium","materiaal":"peddel"}`

Gebruik helptemplate:

```html
<h3>{part_name}</h3>
<p>Onderdeel ID: {part_id}</p>
<p>GPS: {latitude}, {longitude}</p>
<p>Meta: {meta_json}</p>
```

## Testcases

### TC-01 Opslaan helptemplate
1. Open `Survival -> Onderdelen`.
2. Kies bestaand onderdeel en open `Bewerk onderdeel`.
3. Vul `Help HTML-template` met bovenstaande testtemplate.
4. Laat `Help-afbeeldingen` leeg.
5. Klik `Onderdeel opslaan`.

Verwacht:
- Succesmelding `Onderdeel bijgewerkt`.
- Bij heropenen staat helptemplate nog gevuld.
- Preview toont vervangen placeholders.

### TC-02 Placeholder validatie
1. Open hetzelfde onderdeel.
2. Zet in helptemplate een onbekende placeholder, bijvoorbeeld `{onbekend_veld}`.
3. Klik opslaan.

Verwacht:
- Foutmelding met `Onbekende placeholders`.
- Ongeldige template wordt niet opgeslagen.

### TC-03 Afbeeldingen via URL
1. Vul `Help-afbeeldingen` met 2 afbeeldings-URLs, elk op eigen regel.
2. Sla op.
3. Open frontend shortcodepagina.

Verwacht:
- In detailweergave worden 2 afbeeldingen onder helptekst getoond.
- Afbeeldingen hebben lazy loading.

### TC-04 Afbeeldingen via attachment ID
1. Vul `Help-afbeeldingen` met een bestaand media attachment ID.
2. Sla op en open frontend.

Verwacht:
- ID wordt gerenderd als afbeelding-URL.
- Geen PHP-foutmelding.

### TC-05 Desktop navigatie
1. Open frontend op desktopbreedte (> 700px).
2. Klik verschillende onderdelen in de linker lijst.

Verwacht:
- URL krijgt `part_id` query parameter.
- Juiste helptekst en afbeeldingen laden per onderdeel.
- Actieve onderdeel in lijst heeft visuele active-state.

### TC-06 Mobiele navigatie
1. Open frontend op mobiel (<= 700px).
2. Gebruik dropdown `Kies onderdeel`.

Verwacht:
- Navigatie naar gekozen onderdeel.
- Detailweergave past op smal scherm.
- Desktop zijmenu is verborgen.

### TC-07 Vorige/Volgende knoppen
1. Open eerste onderdeel in frontend.
2. Controleer pagerknoppen.
3. Blader door naar laatste onderdeel.

Verwacht:
- Op eerste onderdeel: `Vorige onderdeel` is disabled.
- Op laatste onderdeel: `Volgende onderdeel` is disabled.
- Op tussengelegen onderdelen werken beide knoppen.

### TC-08 Rechtencontrole
1. Login als gebruiker zonder beheerrechten.
2. Open WordPress admin.

Verwacht:
- `Survival -> Onderdelen` niet toegankelijk.
- Frontend helpweergave blijft read-only zichtbaar (zonder admin acties).

## Regressiechecks
- Onderdelen import/export blijft functioneren.
- Onderdeel verwijderen/deactiveren werkt nog volgens bestaande regels.
- Bestaande shortcodes (teams, overview, score) renderen zonder regressie.

## Afronding
Test geslaagd als alle testcases groen zijn en geen nieuwe PHP warnings/notices verschijnen in debug log.
